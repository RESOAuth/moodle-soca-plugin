#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
root=$PWD
runtime="$root/demo/.runtime"
engine=${CONTAINER_ENGINE:-docker}
image='docker.io/library/wordpress@sha256:607e9f6829f9e4f3001bc91c98426aaa742d164af030aa7a8073932798e2af6b'
composer='docker.io/library/composer@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac'
database='docker.io/library/mariadb@sha256:1292844148b311e4ed4300022a996d39083f415a963e970cf47cad1b3b18e3a6'
mkdir -p "$runtime/site"
"$engine" run --rm --network host -v "$root:/app" -w /app "$composer" install --no-interaction --no-progress --ignore-platform-req=ext-curl
if [[ ! -f "$runtime/site/version.php" ]]; then
  curl -fsSL https://github.com/moodle/moodle/archive/215f44380bdc3ccf852a3a22e34f8423ff64033a.tar.gz -o "$runtime/moodle.tar.gz"
  printf '%s  %s\n' a621b03cbc209ada676bacb3611d8d2cc3500c7a561f448101b086ca21f9b4e2 "$runtime/moodle.tar.gz" | sha256sum -c -
  tar -xzf "$runtime/moodle.tar.gz" -C "$runtime/site" --strip-components=1
fi
if ! "$engine" inspect moodle-soca-demo-db > /dev/null 2>&1; then
  "$engine" run -d --name moodle-soca-demo-db -p 127.0.0.1:13407:3306 -e MARIADB_ROOT_PASSWORD=soca-disposable-local-test -e MARIADB_DATABASE=soca_moodle "$database" > /dev/null
else "$engine" start moodle-soca-demo-db > /dev/null; fi
for attempt in $(seq 1 60); do
  if "$engine" exec moodle-soca-demo-db mariadb-admin -uroot -psoca-disposable-local-test ping --silent > /dev/null 2>&1; then break; fi
  sleep 1
done
[[ -L "$runtime/site/auth/soca" ]] || ln -s /plugin "$runtime/site/auth/soca"
run() { "$engine" run --rm --network host -e MOODLE_ROOT=/demo/site -v "$root:/plugin" -v "$runtime:/demo" -w /demo/site "$image" php -d max_input_vars=5000 "$@"; }
if [[ ! -f "$runtime/site/config.php" ]]; then
  run admin/cli/install.php --wwwroot=http://127.0.0.1:18082 --dataroot=/demo/data --dbtype=mariadb --dbhost=127.0.0.1 --dbport=13407 --dbname=soca_moodle --dbuser=root --dbpass=soca-disposable-local-test --fullname='SOCA Moodle demo' --shortname=SOCA --adminpass='Demo-Only-Replace-Me-2841' --adminemail=admin@example.com --non-interactive --agree-license > "$runtime/install.log" 2>&1
fi
run /plugin/tests/host.php
if [[ ${1:-test} == serve ]]; then
  printf 'Local demo: http://127.0.0.1:18082/login/index.php (admin / Demo-Only-Replace-Me-2841)\n'
  run -S 127.0.0.1:18082 -t /demo/site
fi
