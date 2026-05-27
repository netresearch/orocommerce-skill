# Installing k6

Four install paths. Pick one.

## Debian / Ubuntu (APT)

Imports Grafana's signing key into a dedicated keyring and adds the stable APT source:

```bash
sudo gpg -k
sudo gpg --no-default-keyring \
  --keyring /usr/share/keyrings/k6-archive-keyring.gpg \
  --keyserver hkp://keyserver.ubuntu.com:80 \
  --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" \
  | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install -y k6
```

Verify with `k6 version`. The binary lives at `/usr/bin/k6`.

## macOS (Homebrew)

```bash
brew install k6
```

Installs from the core formula (`/opt/homebrew/bin/k6` on Apple Silicon, `/usr/local/bin/k6` on Intel).

## Windows (winget)

```powershell
winget install k6 --source winget
```

Alternative: `choco install k6` if you prefer Chocolatey. The binary lands on `PATH` automatically.

## Docker

```bash
docker pull grafana/k6:latest
```

No local install — the binary runs inside the container. Pin a concrete tag (e.g. `grafana/k6:0.52.0`) for CI. See `docker-invocation.md` for the full `docker run` pattern including network mode and user flags.

## Version Check

Regardless of install path:

```bash
k6 version
# k0.52.0 ((devel), go1.22.5, linux/amd64)
```

Oro's stock scripts target k6 >= 0.50. Older k6 versions may lack `handleSummary()` or recent `options.thresholds` features.
