# PostgreSQL on tmpfs for Faster Behat Runs

Advanced: this is not in the official Oro docs. Verified by experience on Linux developer machines. Do not apply to CI or production hosts.

## Why

Behat's per-scenario isolators truncate and re-seed the database hundreds of times per run. Disk I/O dominates the wall-clock time of the whole suite — moving Postgres' data directory onto a RAM-backed tmpfs cuts a 40-minute run to roughly 15 minutes on typical hardware. The database is ephemeral (re-created from fixtures every run) so losing it on reboot is acceptable.

## Setup (Linux Host)

```bash
# 1. Create the mount point
sudo mkdir -p /var/tmpfs

# 2. Mount 4 GB of RAM there
sudo mount -t tmpfs -o size=4G,mode=1777 tmpfs /var/tmpfs

# 3. Stop Postgres before moving data
sudo systemctl stop postgresql

# 4. Copy the existing cluster into tmpfs
sudo rsync -a /var/lib/postgresql/<version>/main/ /var/tmpfs/postgresql/<version>/main/
sudo chown -R postgres:postgres /var/tmpfs/postgresql

# 5. Point Postgres at the new location
sudo sed -i 's|^data_directory.*|data_directory = '"'"'/var/tmpfs/postgresql/<version>/main'"'"'|' \
    /etc/postgresql/<version>/main/postgresql.conf

# 6. Start Postgres
sudo systemctl start postgresql
```

Replace `<version>` with the installed major (14, 15, 16).

## Persistence Across Reboots

Tmpfs is gone after reboot. Either accept re-seeding fixtures on first run, or add to `/etc/fstab`:

```
tmpfs  /var/tmpfs  tmpfs  defaults,size=4G,mode=1777  0  0
```

Add a systemd oneshot or a `postgresql.service` drop-in that restores the empty directory structure before Postgres starts. Without it, Postgres fails to start on a bare tmpfs.

## AppArmor (Ubuntu/Debian)

On distributions with AppArmor profiles for Postgres, the default profile only allows access under `/var/lib/postgresql/`. Add an alias so the profile recognizes the tmpfs path:

```bash
echo "alias /var/lib/postgresql/ -> /var/tmpfs/postgresql/," | \
    sudo tee /etc/apparmor.d/tunables/alias.d/postgresql-tmpfs
sudo systemctl reload apparmor
```

Without this, Postgres starts but cannot open its own data files.

## Sizing

4 GB is enough for a typical Behat fixture set with headroom. Monitor with `df -h /var/tmpfs` while a suite runs — if usage climbs past ~75% during a run, bump the `size=` value. tmpfs sizing is a ceiling, not a reservation, so 4 GB costs no RAM until used.

## Docker Alternative

If Postgres runs in a container, skip the host-level setup and mount tmpfs directly in compose:

```yaml
services:
  postgres:
    tmpfs:
      - /var/lib/postgresql/data:size=4G
```

This is simpler but loses the data on every container restart. Fine for `behat_test`; do not use it for a dev database you care about.

## Caveats

- Obviously RAM-only — a crash or reboot wipes the database. Non-issue for Behat fixtures.
- Do not apply to CI runners shared with other workloads. The memory pressure is invisible to other jobs.
- `fsync` becomes meaningless; do not benchmark disk tuning with tmpfs in place.
