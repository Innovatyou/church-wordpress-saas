#!/bin/bash
#
# Ministry OS: run WordPress scheduled jobs for every church site (payment checks, emails, reminders).
#
# Hostinger: in File Manager go to your home folder — the top level, where you see the folders
# "domains" and "public_html" (NOT inside wp-admin, wp-content or wp-includes) — and upload this
# file there. Then in hPanel → Advanced → Cron Jobs add a Custom job that runs every 5 minutes
# (*/5 * * * *):
#
#     /bin/bash /home/YOUR-USER/hostinger-cron.sh
#
# (YOUR-USER is the u123456789-style name shown in File Manager's path.)
# The script finds WordPress by itself: the folder with wp-config.php, usually
# ~/domains/ministryos.com.ng/public_html.
#
# What it does:
# - Skips a run if the previous one is still going (no overlapping runs).
# - Finds every site on the network (one site if it is not a multisite) and runs the jobs
#   that are due, each with a 2-minute limit so one slow site cannot hold up the others.
# - Writes one line per run to ~/logs/ministryos-cron.log (kept to the last 2000 lines).
# - Uses WP-CLI when Hostinger provides it, otherwise downloads wp-cli.phar once.
#
# Settings you can change with environment variables:
#   WP_PATH  WordPress folder         (default: found automatically)
#   WP_CLI   WP-CLI command           (default: wp, or php ~/wp-cli.phar)
#   LOG      Log file                 (default: ~/logs/ministryos-cron.log)

LOG="${LOG:-$HOME/logs/ministryos-cron.log}"
LOCK="${LOCK:-$HOME/.ministryos-cron.lock}"

mkdir -p "$(dirname "$LOG")"
stamp() { date '+%Y-%m-%d %H:%M:%S'; }

# The WordPress folder is the one holding wp-config.php. Hostinger keeps each website in
# ~/domains/<domain>/public_html; prefer the multisite install, then ministryos.com.ng.
if [ -z "$WP_PATH" ]; then
	for dir in "$HOME/domains/ministryos.com.ng/public_html" "$HOME/public_html" "$HOME"/domains/*/public_html; do
		if [ -f "$dir/wp-config.php" ] && grep -qE "MULTISITE['\"]?[[:space:]]*,[[:space:]]*true" "$dir/wp-config.php"; then
			WP_PATH="$dir"
			break
		fi
	done
	if [ -z "$WP_PATH" ]; then
		for dir in "$HOME/domains/ministryos.com.ng/public_html" "$HOME/public_html" "$HOME"/domains/*/public_html; do
			if [ -f "$dir/wp-config.php" ]; then
				WP_PATH="$dir"
				break
			fi
		done
	fi
fi

# One run at a time.
exec 9>"$LOCK"
if ! flock -n 9; then
	echo "$(stamp) previous run still going, skipped" >> "$LOG"
	exit 0
fi

# WP-CLI.
if [ -n "$WP_CLI" ]; then
	WP="$WP_CLI"
elif command -v wp >/dev/null 2>&1; then
	WP="wp"
else
	if [ ! -s "$HOME/wp-cli.phar" ]; then
		curl -sSL -o "$HOME/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar || {
			echo "$(stamp) could not download WP-CLI" >> "$LOG"
			exit 1
		}
	fi
	WP="php $HOME/wp-cli.phar"
fi

cd "$WP_PATH" || { echo "$(stamp) WordPress folder not found: $WP_PATH" >> "$LOG"; exit 1; }

start=$(date +%s)
if $WP core is-installed --network >/dev/null 2>&1; then
	urls=$($WP site list --field=url --archived=0 --deleted=0 --spam=0 2>>"$LOG")
else
	urls=$($WP option get home 2>>"$LOG")
fi

sites=0
failed=0
for url in $urls; do
	sites=$((sites + 1))
	if ! timeout 120 $WP cron event run --due-now --url="$url" --quiet >>"$LOG" 2>&1; then
		failed=$((failed + 1))
		echo "$(stamp) $url: jobs did not finish" >> "$LOG"
	fi
done

echo "$(stamp) ran due jobs on $sites site(s), $failed problem(s), in $(( $(date +%s) - start ))s" >> "$LOG"

# Keep the log small.
if [ "$(wc -l < "$LOG")" -gt 2000 ]; then
	tail -n 2000 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
fi
