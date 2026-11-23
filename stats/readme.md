# Download statistics

`fota-stats.py` reads the nginx access log of the web server that hosts the FOTA
server and prints, per day, how many gateways checked for an update, how many
downloaded the firmware, and how often the firmware was downloaded by hand.

The script only reads log files. It needs no change to the web server, to
`index.php` or to the gateway firmware.

## Requirements

- Python 3.6 or newer (standard library only)
- Read access to the nginx access log in the default `combined` format
  (on Debian and Ubuntu: membership of the group `adm`, or `sudo`)

## Use

```sh
# All logs that are still on the server (rotated and compressed ones included)
./fota-stats.py /var/log/nginx/access.log*

# Keep the daily results, so the history outlives the log rotation
./fota-stats.py --state /var/lib/fota-stats/state.json /var/log/nginx/access.log*
```

Example output:

```
date         checks  gateways  updates  aborted   manual  manual_ips
2026-11-20        2         2        1        0        1           1
2026-11-21        4         3        1        1        0           0

Period in the data            : 2026-11-20 .. 2026-11-21 (2 days with requests)
Unique gateways, last day     : 3
Unique gateways, last 7 days  : 4
Unique gateways, last 30 days : 4
Gateway updates, total        : 2 completed, 1 aborted
Manual downloads, total       : 1
```

| Column | Meaning |
|---|---|
| `checks` | Requests for `checksums` by a gateway. A gateway asks once at every start and restarts once per 24 hours to check, so a healthy gateway gives one check per day. |
| `gateways` | Number of different source addresses that sent a gateway request that day. |
| `updates` | Completed downloads of `firmware.hex` by a gateway: the gateway installed a new version. |
| `aborted` | Downloads of `firmware.hex` by a gateway that stopped before the end. The gateway tries again at its next start. |
| `manual` | Completed downloads of `firmware.hex` by anything else (a browser, `curl`, `wget`): somebody fetching the image for an SD card. |
| `manual_ips` | Number of different source addresses behind `manual`. |

### Options

| Option | Purpose |
|---|---|
| `--prefix PATH` | URL path of the FOTA server. Default `/fotasrv`. |
| `--state FILE` | Store the daily results in `FILE` and add new days at every run. |
| `--days N` | Number of days in the table. Default 30. |
| `--csv` | Print the table as CSV (for a spreadsheet or a plot). |
| `--exclude-ip ADDR` | Ignore an address, for example your own test gateway. Repeatable. |
| `--image-size BYTES` | Size of `firmware.hex`. Default: the largest firmware response in the logs. |
| `--keep-hashes DAYS` | With `--state`: how long the hashed addresses are kept. Default 90. |

### Run it every day

Log rotation removes old logs (nginx on Debian and Ubuntu keeps 14 days). With
`--state` the script keeps its own history. A day that is already stored is
only replaced when the logs hold at least as many requests for it, so the
script can read the same logs again at every run.

```cron
# /etc/cron.d/fota-stats
15 0 * * * root /opt/ttksgfotasrv/stats/fota-stats.py --state /var/lib/fota-stats/state.json /var/log/nginx/access.log* > /var/lib/fota-stats/report.txt
```

- Run the script by hand first and check that the table shows requests. A cron
  job that reads the wrong log file runs without an error and stores nothing.
- Once a day is enough. The job must run more often than the logs are kept; a
  missed day is added at the next run while its log is still on the server.
- Adjust the path of the script and of the log files to your server. `root` can
  be any user that may read the nginx logs.
- Create `/var/lib/fota-stats/` first. Do not put the state file or the report
  inside the web root.
- `report.txt` holds the table of the last run.

## No FOTA requests found

When a run finds no request, the script says on standard error how many lines
it read, how many are in the `combined` format and how many name `checksums` or
`firmware.hex`, and shows the first of those lines. The causes:

| The script reports | Cause | What to do |
|---|---|---|
| No line names one of the files | The site writes to another log file | `grep -rn access_log /etc/nginx/` shows the file; run the script on that file and its rotated copies |
| Lines name the files, but are not in the combined format | The site uses its own `log_format` | The script reads the default `combined` format only; `grep -rn log_format /etc/nginx/` shows the format in use |
| Lines name the files and are readable | The requests have another path, or the address is excluded | Compare the path in the line shown with `--prefix`; check `--exclude-ip` |

## How a request is counted

- Only `GET` requests with status 200 for `<prefix>/checksums` and
  `<prefix>/firmware.hex` count. The information page, `HEAD` requests and
  errors are ignored.
- A request is from a gateway when its `User-Agent` is exactly `TTNGateway`.
  That is the only thing the firmware sends about itself (see
  [the protocol specification](../TTKSGFOTASpecification.md), section 3).
- A firmware download is complete when the server sent at least 98 % of the
  image. The image size is the largest firmware response in the logs, or
  `--image-size`.

## Limits of the numbers

- **A gateway is a source address.** The firmware sends no serial number, so
  two gateways behind one internet connection count as one, and a gateway whose
  address changes counts as a new one. The daily number is the most reliable;
  the 30-day number is an upper estimate where providers change addresses.
- A gateway that is switched off, or that cannot reach the server, is not
  counted that day.
- Anybody can send `User-Agent: TTNGateway`. The numbers are for monitoring,
  not for proof.
- A download that stopped in the last 2 % of the image counts as complete.
- The day is the day in the log, in the time zone of the server.

## Privacy

IP addresses are personal data. The script never prints or stores an address.
With `--state` it stores, per day, a keyed hash (HMAC-SHA-256, 64 bits kept) of
every gateway address; the key is random and is in the state file. Hashes older
than `--keep-hashes` days are deleted and only the counts remain. Without
`--state` nothing is stored and the key is discarded when the script ends.

The state file is created with mode 600. Whoever has the state file can test
whether a given address is in it, so treat it like the access log itself.
