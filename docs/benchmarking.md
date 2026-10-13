# Benchmarking

BXCode ships a small in-process benchmark that measures the cost of the request
lifecycle (`Application::handle()`), isolated from the web server and network.

```bash
php bin/benchmark.php [iterations]   # default 3000
```

## What it measures

For each scenario it dispatches a `Request` through the real router and
middleware pipeline and records per-iteration wall time (`hrtime`), then reports
the mean, p50, and p95 in milliseconds, plus the database query count and peak
memory.

## Sample run

Numbers below are from one development machine (Apple Silicon, PHP 8.5.2, sqlite
in-memory, opcache off, 3000 iterations). **They are illustrative — re-run on
your own hardware for meaningful figures.**

| Scenario          | mean (ms) | p50 (ms) | p95 (ms) | queries |
|-------------------|-----------|----------|----------|---------|
| plain text        | 0.0008    | 0.0008   | 0.0009   | 0       |
| route parameter   | 0.0012    | 0.0012   | 0.0013   | 0       |
| json response     | 0.0012    | 0.0012   | 0.0013   | 0       |
| database-backed   | 0.0136    | 0.0115   | 0.0155   | 1       |
| template render   | 0.0802    | 0.0537   | 0.1290   | 0       |
| middleware stack  | 0.0032    | 0.0032   | 0.0032   | 0       |

Peak memory: ~4 MB.

Observations: routing and JSON are effectively free; the database scenario is
dominated by the single query; template rendering is the heaviest path (file
include + output buffering through a layout and a partial).

## Honest caveats

- This is an **in-process micro-benchmark**. It excludes PHP startup, the web
  server, FastCGI, TLS, and network — real request latency on shared hosting
  will be higher and dominated by those.
- Results depend heavily on hardware, PHP version, and opcache. Enable opcache
  in production.
- The database scenario uses in-memory sqlite; MySQL over a socket/network will
  be slower per query.
- **No comparison to Laravel, CodeIgniter, or any other framework is made or
  implied.** Comparative claims require identical workloads, hardware, and
  methodology, which this tool does not provide.
