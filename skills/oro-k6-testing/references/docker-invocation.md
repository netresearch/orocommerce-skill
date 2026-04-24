# Running k6 via the grafana/k6 Docker Image

The `grafana/k6` image is a tiny Alpine container shipping the `k6` binary at `/usr/bin/k6` as entrypoint. Any `k6` subcommand (`run`, `archive`, `inspect`, `cloud`) works as the first argument.

## Canonical Invocation

```bash
docker run --rm --network host -u "$(id -u):$(id -g)" \
  -v "${PWD}/performance:/home/k6/performance" -w /home/k6/performance \
  grafana/k6:latest run \
  -e BASE_URL="https://oro.docker.local" \
  -e USERNAME="AmandaRCole@example.org" \
  -e PASSWORD="AmandaRCole@example.org" \
  -e VU=1 -e DURATION=60s -e THRESHOLD_95=3000 \
  scripts/warmingUpTheApp.js
```

## Flags Explained

| Flag | Why |
|---|---|
| `--rm` | Remove the container when it exits — we want fresh state on every run, no layer accumulation. |
| `--network host` | Share the host's network namespace. k6 inside the container can reach `localhost:80`, `127.0.0.1`, and any `*.docker.local` hostname that resolves on the host. Without this, `bridge` is used and the container can't reach host ports or compose services bound only to `127.0.0.1`. |
| `-u "$(id -u):$(id -g)"` | Run k6 as your user, not as root. Any files k6 writes into the mounted volume (notably `summary.html` from `k6-reporter`) land owned by you instead of root, so the next run can overwrite them and you can `rm` them without `sudo`. |
| `-v "${PWD}/performance:/home/k6/performance"` | Bind-mount the local `performance/` directory into the container. k6 needs the scripts and also writes the HTML summary back out here. |
| `-w /home/k6/performance` | Set the working directory so the `scripts/...js` script path argument resolves and `handleSummary()` writes `summary.html` into the mounted directory (not `/`). |
| `grafana/k6:latest` | The image. Pin a specific tag (e.g. `grafana/k6:0.52.0`) for CI stability — `latest` drifts. |
| `run` | The `k6` subcommand. Must come before `-e` flags; they're arguments to `run`, not to `docker`. |
| `-e KEY=VALUE` | Each one sets `__ENV.KEY` in the script. Repeat for every variable the script reads. |
| `scripts/warmingUpTheApp.js` | Script path, resolved relative to `-w`. |

## Network Mode Alternatives

- **Host network (shown above)** — simplest when the target is on `localhost` or in `/etc/hosts`. Only works on Linux; on macOS/Windows Docker Desktop, `--network host` is a no-op.
- **Attached to a compose network** — use when the target lives in a compose stack:
  ```bash
  docker run --rm --network oro_default \
    -v "${PWD}/performance:/home/k6/performance" -w /home/k6/performance \
    -u "$(id -u):$(id -g)" grafana/k6:latest run \
    -e BASE_URL="http://nginx" ... scripts/storefrontTests.js
  ```
  The hostname (`nginx`) must be a compose service on `oro_default`.
- **`host.docker.internal`** — on macOS/Windows Docker Desktop, use `BASE_URL="http://host.docker.internal"` instead of `localhost` with default bridge network. On Linux, add `--add-host=host.docker.internal:host-gateway`.

## File Ownership Pitfall

Without `-u`, k6 runs as root inside the container. `summary.html` written through the bind mount keeps root ownership on the host:

```
-rw-r--r-- 1 root root 412K Apr 11 14:22 summary.html
```

The next run (as your user, with `-u` added) gets `EACCES` on write. Fix by `sudo rm summary.html` once, then always include `-u "$(id -u):$(id -g)"`. There is no reason to ever run k6 as root.

## TLS Verification

Oro dev environments usually have self-signed certs. k6 fails TLS verification by default. Add `--insecure-skip-tls-verify` to the `run` args or set `insecureSkipTLSVerify: true` in `options`.
