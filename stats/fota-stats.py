#!/usr/bin/env python3
"""Download statistics for the FOTA server, from the nginx access log.

Counts per day how many gateways checked for an update, how many downloaded
the firmware and how often the firmware was downloaded by hand (SD card).
See stats/readme.md for the details and the limits of the numbers.

    fota-stats.py /var/log/nginx/access.log*
    fota-stats.py --state /var/lib/fota-stats/state.json /var/log/nginx/access.log*

Python 3.6 or newer, standard library only. The script does not change
anything on the web server; it only reads the log files.
"""

import argparse
import csv
import datetime
import gzip
import hashlib
import hmac
import json
import os
import re
import secrets
import sys

GATEWAY_AGENT = "TTNGateway"   # User-Agent the gateway firmware sends
COMPLETE_RATIO = 0.98          # share of the image that counts as a complete download

# nginx "combined" log format; extra fields after the user agent are ignored.
LINE = re.compile(
    r'^(\S+) \S+ \S+ \[(\d{2})/(\w{3})/(\d{4}):[^\]]*\] '
    r'"(\S+) (\S+)[^"]*" (\d{3}) (\d+|-) "[^"]*" "([^"]*)"')
MONTHS = {m: i + 1 for i, m in enumerate(
    "Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec".split())}


def open_log(name):
    if name == "-":
        return sys.stdin
    if name.endswith(".gz"):
        return gzip.open(name, "rt", errors="replace")
    return open(name, "r", errors="replace")


def read_logs(names, prefix, exclude):
    """Yield (day, ip, resource, is_gateway, bytes_sent) for every successful
    GET of one of the two FOTA files."""
    targets = {prefix + "/checksums": "checksums",
               prefix + "/firmware.hex": "firmware"}
    for name in names:
        with open_log(name) as log:
            for line in log:
                m = LINE.match(line)
                if not m:
                    continue
                ip, dd, mon, yyyy, method, uri, status, size, agent = m.groups()
                if method != "GET" or status != "200" or ip in exclude:
                    continue
                # The gateway can send "/fotasrv//checksums"; ignore a query string.
                path = re.sub(r"/{2,}", "/", uri.split("?", 1)[0])
                resource = targets.get(path)
                if resource is None or mon not in MONTHS:
                    continue
                day = "%s-%02d-%s" % (yyyy, MONTHS[mon], dd)
                yield (day, ip, resource, agent == GATEWAY_AGENT,
                       0 if size == "-" else int(size))


def collect(names, prefix, exclude, salt, image_size):
    """Return ({day: record}, largest firmware response seen)."""
    key = salt.encode()
    hits = list(read_logs(names, prefix, exclude))
    largest = max([image_size] + [h[4] for h in hits if h[2] == "firmware"])
    complete_from = largest * COMPLETE_RATIO

    days = {}
    for day, ip, resource, is_gateway, size in hits:
        rec = days.setdefault(day, {
            "lines": 0, "checks": 0, "updates": 0, "aborted": 0, "manual": 0,
            "gw": set(), "man": set()})
        rec["lines"] += 1
        ident = hmac.new(key, ip.encode(), hashlib.sha256).hexdigest()[:16]
        if is_gateway:
            rec["gw"].add(ident)
            if resource == "checksums":
                rec["checks"] += 1
            elif size >= complete_from:
                rec["updates"] += 1
            else:
                rec["aborted"] += 1
        elif resource == "firmware" and size >= complete_from:
            rec["manual"] += 1
            rec["man"].add(ident)

    for rec in days.values():
        rec["gw"] = sorted(rec["gw"])
        rec["ngw"] = len(rec["gw"])
        rec["nman"] = len(rec.pop("man"))
    return days, largest


def load_state(path):
    if path and os.path.exists(path):
        with open(path) as f:
            return json.load(f)
    return {"salt": secrets.token_hex(16), "image_size": 0, "days": {}}


def save_state(path, state):
    tmp = path + ".tmp"
    fd = os.open(tmp, os.O_WRONLY | os.O_CREAT | os.O_TRUNC, 0o600)
    with os.fdopen(fd, "w") as f:
        json.dump(state, f, indent=1, sort_keys=True)
    os.replace(tmp, path)


def merge(state, days, keep_hashes):
    """Add the days from this run to the stored ones. A day that is already
    stored is only replaced when the logs of this run hold at least as many
    requests for it (a rotated log may hold only part of a day)."""
    stored = state["days"]
    for day, rec in days.items():
        if day not in stored or rec["lines"] >= stored[day]["lines"]:
            stored[day] = rec
    if stored:
        newest = datetime.date.fromisoformat(max(stored))
        limit = (newest - datetime.timedelta(days=keep_hashes)).isoformat()
        for day, rec in stored.items():
            if day <= limit:
                rec["gw"] = None      # keep the counts, drop the identifiers


def unique_gateways(days, last_day, window):
    """Unique gateways in the `window` days up to and including last_day, or
    None when identifiers of part of that period are no longer stored."""
    end = datetime.date.fromisoformat(last_day)
    seen = set()
    for i in range(window):
        rec = days.get((end - datetime.timedelta(days=i)).isoformat())
        if rec is None:
            continue
        if rec["gw"] is None:
            return None
        seen.update(rec["gw"])
    return len(seen)


COLUMNS = [("date", "day"), ("checks", "checks"), ("gateways", "ngw"),
           ("updates", "updates"), ("aborted", "aborted"),
           ("manual", "manual"), ("manual_ips", "nman")]


def report(days, show, as_csv, out):
    if not days:
        print("No FOTA requests found in the log files.", file=out)
        return
    names = sorted(days)
    rows = [[d] + [days[d][key] for _, key in COLUMNS[1:]] for d in names[-show:]]

    if as_csv:
        w = csv.writer(out)
        w.writerow([title for title, _ in COLUMNS])
        w.writerows(rows)
        return

    print("%-10s %8s %9s %8s %8s %8s %11s" % tuple(t for t, _ in COLUMNS), file=out)
    for row in rows:
        print("%-10s %8d %9d %8d %8d %8d %11d" % tuple(row), file=out)

    last = names[-1]
    print(file=out)
    print("Period in the data            : %s .. %s (%d days with requests)"
          % (names[0], last, len(names)), file=out)
    for window in (1, 7, 30):
        n = unique_gateways(days, last, window)
        label = "last day" if window == 1 else "last %d days" % window
        print("Unique gateways, %-13s: %s"
              % (label, "not available" if n is None else n), file=out)
    print("Gateway updates, total        : %d completed, %d aborted"
          % (sum(r["updates"] for r in days.values()),
             sum(r["aborted"] for r in days.values())), file=out)
    print("Manual downloads, total       : %d"
          % sum(r["manual"] for r in days.values()), file=out)
    print(file=out)
    print("A gateway is a source address that sent User-Agent \"%s\"; gateways\n"
          "behind one address count as one. See stats/readme.md." % GATEWAY_AGENT,
          file=out)


def main():
    p = argparse.ArgumentParser(
        description="Download statistics for the FOTA server from nginx access logs.")
    p.add_argument("logs", nargs="+",
                   help="access log files (plain or .gz), or - for standard input")
    p.add_argument("--prefix", default="/fotasrv",
                   help="URL path of the FOTA server (default: %(default)s)")
    p.add_argument("--state", metavar="FILE",
                   help="keep the daily results in this file, so the history "
                        "outlives the log rotation")
    p.add_argument("--days", type=int, default=30,
                   help="number of days in the table (default: %(default)s)")
    p.add_argument("--csv", action="store_true", help="print the table as CSV")
    p.add_argument("--exclude-ip", action="append", default=[], metavar="ADDR",
                   help="ignore requests from this address (repeatable)")
    p.add_argument("--image-size", type=int, default=0, metavar="BYTES",
                   help="size of firmware.hex; default: the largest firmware "
                        "response found")
    p.add_argument("--keep-hashes", type=int, default=90, metavar="DAYS",
                   help="with --state: days the hashed addresses are kept "
                        "(default: %(default)s); the counts are kept forever")
    args = p.parse_args()

    if args.days < 1 or args.keep_hashes < 1:
        p.error("--days and --keep-hashes must be at least 1")
    prefix = "/" + args.prefix.strip("/") if args.prefix.strip("/") else ""

    state = load_state(args.state)
    days, largest = collect(args.logs, prefix, set(args.exclude_ip), state["salt"],
                            max(args.image_size, state.get("image_size", 0)))
    state["image_size"] = largest
    merge(state, days, args.keep_hashes)
    if args.state:
        save_state(args.state, state)
    report(state["days"], args.days, args.csv, sys.stdout)


if __name__ == "__main__":
    main()
