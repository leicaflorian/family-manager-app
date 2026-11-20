#!/usr/bin/env bash
# shellcheck disable=SC2329 # the assertion helpers run through check(), which shellcheck cannot follow
# Smoke test of ./release. Every scenario runs in its own temporary directory with a bare remote,
# a clone and stubs for claude, artisan and ssh (the ssh stub runs the real remote dump command
# against a fake docker and mysqldump). The real repository and its remote are never touched.
# Usage: bash tests/release/smoke.sh   (exits non-zero when a scenario fails)
set -u

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
FAILED=0
# Keep the owner's global git configuration (signing, hooks) out of the fixtures.
export GIT_CONFIG_GLOBAL=/dev/null
export GIT_CONFIG_NOSYSTEM=1
unset MYSQL_ROOT_PASSWORD

# Writes the stubs into $T/bin and the fakes of the droplet into $T/fakebin.
write_stubs() {
    mkdir -p "$T/bin" "$T/fakebin"

    cat > "$T/bin/claude" <<'EOF'
#!/usr/bin/env bash
echo "claude $*" >> "$STUB_ROOT/calls.log"
cat > "$STUB_ROOT/claude-stdin"
case "${STUB_CLAUDE:-ok}" in
    fail) echo "API error" >&2; exit 1 ;;
    error) echo '{"type":"result","subtype":"error_during_execution","is_error":true,"result":"Something broke","structured_output":null}'; exit 0 ;;
esac
cat <<'JSON'
{"type":"result","subtype":"success","is_error":false,"num_turns":2,"result":"{}","structured_output":{"bump":"minor","reason":"Something new.","changelog":"### Agenda\n- New agenda","notes":{"it":["Agenda nuova"],"en":["New agenda"]}},"total_cost_usd":0.1}
JSON
EOF

    # Called as "<sail> artisan app:release storage/app/release/response.json".
    cat > "$T/bin/sail" <<'EOF'
#!/usr/bin/env bash
echo "sail $*" >> "$STUB_ROOT/calls.log"
[ "$1" = artisan ] && [ "$2" = app:release ] && [ "$3" = storage/app/release/response.json ] || { echo "bad args: $*" >&2; exit 2; }
jq -e '.bump == "minor" and (.notes.it | length) == 1' "$3" > /dev/null || { echo "bad response.json" >&2; exit 1; }
[ "${STUB_ARTISAN:-ok}" = cancel ] && { echo "Cancelled."; exit 1; }
v=${STUB_VERSION:-2.0.0}
printf '%s\n' "$v" > VERSION
printf '# Changelog\n\n## [%s] - 2026-11-15\n\n### Agenda\n- New agenda\n' "$v" > CHANGELOG.md
printf '[{"version":"%s"}]\n' "$v" > resources/release/notes.json
printf '%s' "$v" > storage/app/release/version
printf '## [%s] - 2026-11-15\n\n### Agenda\n- New agenda\n' "$v" > storage/app/release/tag-message.md
exit 0
EOF

    # Called as "<ssh> family-manager-prod <remote command>": runs the command like the droplet would,
    # with /root/backups moved into the temporary directory and the fakes first on PATH.
    cat > "$T/bin/ssh" <<'EOF'
#!/usr/bin/env bash
host=$1; shift
echo "ssh $host" >> "$STUB_ROOT/calls.log"
[ "${STUB_SSH:-ok}" = fail ] && { echo "ssh: connect to host $host: Connection refused" >&2; exit 255; }
# steal: leave the clone on dev with main checked out elsewhere, so the cleanup cannot check out main.
if [ "${STUB_SSH:-ok}" = steal ]; then
    git checkout -q dev && git worktree add -q "$STUB_ROOT/elsewhere" main 2> /dev/null
    exit 255
fi
cmd="$*"
cmd=${cmd//\/root\/backups/$STUB_ROOT/backups}
# The path printed back is the one on the droplet, as the script expects.
set -o pipefail
PATH="$STUB_ROOT/fakebin:$PATH" sh -c "$cmd" | sed "s#$STUB_ROOT/backups#/root/backups#"
EOF

    # Called as "<checks>": the checks of the CI, before anything is touched.
    cat > "$T/bin/checks" <<'EOF'
#!/usr/bin/env bash
echo "checks $(git symbolic-ref --short HEAD) $(git rev-parse HEAD)" >> "$STUB_ROOT/calls.log"
[ "${STUB_CHECKS:-ok}" = fail ] && { echo "Formatting issues found"; exit 1; }
exit 0
EOF

    cat > "$T/fakebin/docker" <<'EOF'
#!/usr/bin/env bash
case "$1" in
    ps) case "$*" in *xwdxjjleppacin2ses4d93xe*) echo "abc123" ;; esac ;;
    exec)
        shift
        [ "$1" = abc123 ] || { echo "no such container $1" >&2; exit 1; }
        shift
        MYSQL_ROOT_PASSWORD='s3cr et$x' "$@" ;;
    *) exit 1 ;;
esac
EOF

    cat > "$T/fakebin/mysqldump" <<'EOF'
#!/usr/bin/env bash
[ "$2" = '-ps3cr et$x' ] || { echo "mysqldump: wrong password argument" >&2; exit 2; }
case "$*" in *"--single-transaction --no-tablespaces family_manager_app"*) ;; *) echo "mysqldump: bad args" >&2; exit 2 ;; esac
echo "-- MySQL dump"
echo "CREATE TABLE things (id int);"
[ "${STUB_DUMP:-ok}" = truncated ] && exit 0
echo "-- Dump completed on 2026-11-15 10:00:00"
EOF

    chmod +x "$T"/bin/* "$T"/fakebin/*
}

# Builds a fresh fixture: bare remote, clone with main and dev. $1 = "tag" to start from a released v2.0.0.
new_fixture() {
    T=$(mktemp -d "${TMPDIR:-/tmp}/release-smoke.XXXXXX")
    export STUB_ROOT="$T"
    write_stubs
    git init -q --bare -b main "$T/remote.git"
    git clone -q "$T/remote.git" "$T/work" 2> /dev/null
    cd "$T/work" || exit 1
    git config user.name Smoke
    git config user.email smoke@example.test
    cp "$ROOT/release" release 2> /dev/null
    mkdir -p resources/release database/migrations storage/app
    cp "$ROOT"/resources/release/prompt.md "$ROOT"/resources/release/response.schema.json resources/release/
    printf 'storage/app/release/\n' > .gitignore
    echo "<?php" > database/migrations/2026_01_01_000000_create_things.php
    git add -A && git commit -q -m "Initial"
    if [ "${1:-}" = tag ]; then
        printf '2.0.0\n' > VERSION
        git add VERSION && git commit -q -m "Release v2.0.0"
        git tag -a v2.0.0 -m "v2.0.0"
    fi
    git push -q origin main --follow-tags
    git checkout -q -b dev
    git push -q -u origin dev 2> /dev/null
    if [ "${1:-}" != tag ] || [ "${2:-}" != empty ]; then
        echo "agenda" > agenda.txt
        git add agenda.txt && git commit -q -m "Add the agenda"
        git push -q origin dev
    fi
}

# Runs ./release in the fixture with the stubs; output in $T/out, exit code in $CODE.
run_release() {
    env RELEASE_CLAUDE="$T/bin/claude" RELEASE_ARTISAN="$T/bin/sail artisan" \
        RELEASE_SSH="$T/bin/ssh family-manager-prod" RELEASE_REMOTE=origin RELEASE_CHECKS="$T/bin/checks" \
        "$@" bash ./release > "$T/out" 2>&1 < /dev/null &
    echo $! > "$T/release.pid"
    wait $!
    CODE=$?
}

ERRORS=""
check() { # check "<description>" <command…>
    local what=$1; shift
    "$@" > /dev/null 2>&1 || ERRORS="$ERRORS
    - $what"
}
called() { grep -q "^$1" "$T/calls.log" 2> /dev/null; }
not_called() { ! called "$1"; }
equal() { [ "$(git rev-parse "$1")" = "$(git rev-parse "$2")" ]; }
on_dev() { [ "$(git symbolic-ref --short HEAD)" = dev ]; }
clean_tree() { [ -z "$(git status --porcelain)" ]; }
no_merge_open() { [ ! -e .git/MERGE_HEAD ]; }
contains() { grep -q "$1" "$T/out"; }

# The checks shared by every scenario that must leave the repository as it was.
restored() {
    check "exit code not 0" test "$CODE" -ne 0
    check "on dev" on_dev
    check "main equals origin/main" equal main origin/main
    check "remote main untouched" equal "$1" "$(git ls-remote "$T/remote.git" refs/heads/main | cut -f1)"
    check "clean tree" clean_tree
    check "no merge open" no_merge_open
}

finish() { # finish "<scenario>"
    if [ -z "$ERRORS" ]; then
        echo "PASS  $1"
    else
        echo "FAIL  $1$ERRORS"
        sed 's/^/      | /' "$T/out"
        FAILED=1
    fi
    ERRORS=""
    cd "$ROOT" || exit 1
    rm -rf "$T"
}

# 1. Happy path, first release: every migration counts, so the database is dumped.
new_fixture
dev_start=$(git rev-parse dev)
run_release
git fetch -q origin
check "exit code 0" test "$CODE" -eq 0
check "checks ran on dev, before the merge" grep -q "^checks dev $dev_start" "$T/calls.log"
check "checks ran before claude" sh -c "head -n 1 '$T/calls.log' | grep -q '^checks'"
check "release commit on remote main" test "$(git log -1 --format=%s origin/main)" = "Release v2.0.0"
check "release commit on top of the merge" test "$(git log -1 --format=%s origin/main^)" = "Merge branch 'dev' for release"
check "merge has two parents" git rev-parse -q --verify "origin/main^^2"
check "tag v2.0.0 is annotated" test "$(git cat-file -t v2.0.0)" = tag
check "tag on the release commit" equal "v2.0.0^{commit}" origin/main
check "tag pushed" git ls-remote --exit-code "$T/remote.git" refs/tags/v2.0.0
check "tag message is the changelog section" sh -c "git tag -l --format='%(contents)' v2.0.0 | grep -q '## \[2.0.0\]'"
check "remote dev equals main" equal origin/dev origin/main
check "local dev equals main" equal dev origin/main
check "on dev" on_dev
check "clean tree" clean_tree
check "ssh called" called ssh
check "dump file written and complete" sh -c "gunzip -c '$T'/backups/family-manager-app/before-v2.0.0-*.sql.gz | tail -n 1 | grep -q '^-- Dump completed'"
check "claude got the commit subjects" grep -q "Add the agenda" "$T/claude-stdin"
check "response.json from structured_output" sh -c "jq -e '.bump == \"minor\"' storage/app/release/response.json"
check "password never printed" sh -c "! grep -q 's3cr' '$T/out'"
finish "1. happy path, first release, dump before the push"

# 2. No new migration since the last tag: no dump.
new_fixture tag
run_release STUB_VERSION=2.1.0
git fetch -q origin
check "exit code 0" test "$CODE" -eq 0
check "release commit on remote main" test "$(git log -1 --format=%s origin/main)" = "Release v2.1.0"
check "tag v2.1.0" git ls-remote --exit-code "$T/remote.git" refs/tags/v2.1.0
check "ssh not called" not_called ssh
check "claude got the commit body format" grep -q "Add the agenda" "$T/claude-stdin"
check "on dev" on_dev
finish "2. no new migration, no dump"

# 2b. A migration added since the last tag: dump.
new_fixture tag
echo "<?php" > database/migrations/2026_11_01_000000_create_more.php
git add -A && git commit -q -m "Add a migration" && git push -q origin dev
run_release STUB_VERSION=2.1.0
check "exit code 0" test "$CODE" -eq 0
check "ssh called" called ssh
finish "2b. new migration since the last tag, dump"

# 2c. A check fails: nothing is merged and Claude is not asked.
new_fixture
before=$(git rev-parse origin/main)
run_release STUB_CHECKS=fail
check "exit code not 0" test "$CODE" -ne 0
check "checks called" called checks
check "claude not called" not_called claude
check "main untouched" equal main "$before"
check "remote main untouched" equal origin/main "$before"
check "on dev" on_dev
check "clean tree" clean_tree
check "says the checks failed" contains "checks failed"
check "does not say Restoring" sh -c "! grep -q Restoring '$T/out'"
finish "2c. a check fails, nothing touched"

# 3. claude fails.
new_fixture
before=$(git rev-parse origin/main)
run_release STUB_CLAUDE=fail
restored "$before"
check "claude called" called claude
check "artisan not called" not_called sail
finish "3. claude fails, everything restored"

# 3b. claude answers with is_error.
new_fixture
before=$(git rev-parse origin/main)
run_release STUB_CLAUDE=error
restored "$before"
check "claude called" called claude
check "says Claude's error" contains "Something broke"
check "artisan not called" not_called sail
finish "3b. claude answers with an error, everything restored"

# 4. artisan cancelled.
new_fixture
before=$(git rev-parse origin/main)
run_release STUB_ARTISAN=cancel
restored "$before"
check "artisan called" called sail
check "no local tag" sh -c "[ -z \"\$(git tag -l 'v*')\" ]"
finish "4. release cancelled, everything restored"

# 5. ssh fails, after the release commit and the tag.
new_fixture
before=$(git rev-parse origin/main)
run_release STUB_SSH=fail
restored "$before"
check "ssh called" called ssh
check "no local tag" sh -c "[ -z \"\$(git tag -l 'v*')\" ]"
check "VERSION gone" test ! -e VERSION
check "remote dev untouched" equal dev origin/dev
finish "5. ssh fails, everything restored"

# 5b. dump incomplete (no "-- Dump completed").
new_fixture
before=$(git rev-parse origin/main)
run_release STUB_DUMP=truncated
restored "$before"
check "ssh called" called ssh
check "says the dump is not complete" contains "not complete"
check "no local tag" sh -c "[ -z \"\$(git tag -l 'v*')\" ]"
finish "5b. incomplete dump, everything restored"

# 6. dirty tree.
new_fixture
before=$(git rev-parse origin/main)
echo "draft" > draft.txt
run_release
check "exit code not 0" test "$CODE" -ne 0
check "main untouched" equal main "$before"
check "on dev" on_dev
check "claude not called" not_called claude
check "draft kept" test -e draft.txt
check "says the tree is not clean" contains "uncommitted"
finish "6. dirty tree, refused"

# 6b. dev behind origin/dev.
new_fixture
before=$(git rev-parse origin/main)
git clone -q "$T/remote.git" "$T/other" 2> /dev/null
(cd "$T/other" && git config user.name Smoke && git config user.email s@e.test && git checkout -q dev \
    && echo x > x.txt && git add x.txt && git commit -q -m "Elsewhere" && git push -q origin dev)
run_release
check "exit code not 0" test "$CODE" -ne 0
check "main untouched" equal main "$before"
check "on dev" on_dev
check "claude not called" not_called claude
check "says dev is not origin/dev" contains "origin/dev"
finish "6b. dev behind origin/dev, refused"

# 7. nothing since the last tag.
new_fixture tag empty
before=$(git rev-parse origin/main)
run_release
check "says Nothing to release" contains "Nothing to release"
check "checks not called" not_called checks
check "claude not called" not_called claude
check "main untouched" equal main "$before"
check "on dev" on_dev
finish "7. nothing to release"

# 8. main conflicts with dev.
new_fixture
git checkout -q main
echo "main version" > agenda.txt
git add agenda.txt && git commit -q -m "Hotfix on main" && git push -q origin main
git checkout -q dev
before=$(git rev-parse origin/main)
run_release
restored "$before"
check "claude not called" not_called claude
check "says the merge conflicts" contains "conflict"
finish "8. conflicting main, merge aborted"

# 9. The remote accepts main but rejects the tag: the atomic push lands nothing, so restoring is right.
new_fixture
before=$(git rev-parse origin/main)
cat > "$T/remote.git/hooks/update" <<'HOOK'
#!/usr/bin/env bash
case "$1" in refs/tags/*) echo "tags are not allowed" >&2; exit 1 ;; esac
HOOK
chmod +x "$T/remote.git/hooks/update"
run_release
restored "$before"
check "no remote tag" sh -c "! git ls-remote --exit-code '$T/remote.git' 'refs/tags/*'"
check "no local tag" sh -c "[ -z \"\$(git tag -l 'v*')\" ]"
finish "9. remote rejects the tag, atomic push, everything restored"

# 10. Ctrl-C after the remote accepted the push: nothing is undone.
new_fixture
cat > "$T/remote.git/hooks/post-receive" <<HOOK
#!/usr/bin/env bash
# TERM: a background job of a non-interactive shell ignores INT; the script traps both alike.
kill -TERM "\$(cat '$T/release.pid')"
HOOK
chmod +x "$T/remote.git/hooks/post-receive"
run_release
git fetch -q origin
check "exit code not 0" test "$CODE" -ne 0
check "release pushed" test "$(git log -1 --format=%s origin/main)" = "Release v2.0.0"
check "says it is pushed" contains "are pushed"
check "does not say Restoring" sh -c "! grep -q Restoring '$T/out'"
check "local main kept the release" equal main origin/main
check "local tag kept" git rev-parse -q --verify refs/tags/v2.0.0
finish "10. interrupted after the push, nothing undone"

# 11. main cannot be checked out during the cleanup: dev is never reset.
new_fixture
dev_before=$(git rev-parse dev)
run_release STUB_SSH=steal
check "exit code not 0" test "$CODE" -ne 0
check "dev untouched" test "$(git rev-parse dev)" = "$dev_before"
check "says main could not be checked out" contains "Could not check out main"
check "remote main untouched" equal origin/main "$(git ls-remote "$T/remote.git" refs/heads/main | cut -f1)"
check "no local tag" sh -c "[ -z \"\$(git tag -l 'v*')\" ]"
finish "11. main cannot be checked out, dev never reset"

# 12. Run from a worktree of the clone: refused before anything is touched.
new_fixture
before=$(git rev-parse origin/main)
git worktree add -q --detach "$T/wt" dev
cd "$T/wt" || exit 1
run_release
check "exit code not 0" test "$CODE" -ne 0
check "claude not called" not_called claude
check "says it needs the main checkout" contains "main checkout"
cd "$T/work" || exit 1
check "main untouched" equal main "$before"
finish "12. worktree, refused"

exit $FAILED
