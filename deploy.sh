#!/usr/bin/env bash
#
# Deploy pe server: trimite commit-urile pe GitHub, pornește workflow-ul „Deploy” și urmărește rularea în terminal.
# Folosire: deploy_baile        (alias, vezi docs/DEPLOY.md)       sau:  ./deploy.sh
#   deploy_baile --fara-mentenanta   (fără pagina de mentenanță în timpul urcării)
# La `git push` simplu NU se face deploy; deploy-ul pornește doar când rulezi acest script.
#
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

WORKFLOW="deploy.yml"
BRANCH="main"
MAINTENANCE="true"

for arg in "$@"; do
    case "$arg" in
        --fara-mentenanta) MAINTENANCE="false" ;;
        *) printf 'Argument necunoscut: %s\nFolosire: deploy_baile [--fara-mentenanta]\n' "$arg" >&2; exit 2 ;;
    esac
done

bold=$'\033[1m'; green=$'\033[32m'; red=$'\033[31m'; yellow=$'\033[33m'; dim=$'\033[2m'; reset=$'\033[0m'
say()  { printf '%s\n' "${bold}$*${reset}"; }
ok()   { printf '%s\n' "${green}✔ $*${reset}"; }
warn() { printf '%s\n' "${yellow}! $*${reset}"; }
fail() { printf '%s\n' "${red}✖ $*${reset}" >&2; exit 1; }

# 1. Cerințe
command -v git >/dev/null || fail "git nu este instalat."
command -v gh >/dev/null || fail "GitHub CLI (gh) nu este instalat. Instalează-l cu: brew install gh  apoi: gh auth login"
gh auth status >/dev/null 2>&1 || fail "Nu ești autentificat în GitHub CLI. Rulează: gh auth login"

# 2. Doar din main
current="$(git rev-parse --abbrev-ref HEAD)"
[ "$current" = "$BRANCH" ] || fail "Ești pe branch-ul „$current”. Deploy-ul se face doar din „$BRANCH”: git checkout $BRANCH"

# 3. Modificări necomise: se deploy-uiește doar ce e commit-uit
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    warn "Ai modificări necomise (nu intră în deploy):"
    git status --short --untracked-files=no | sed 's/^/    /'
    read -r -p "Continui doar cu ce e commit-uit? [d/N] " answer
    case "$answer" in d|D|da|DA|y|Y) ;; *) fail "Anulat. Salvează modificările în git și rulează din nou deploy_baile." ;; esac
fi

# 4. Push
say "→ Trimit commit-urile pe GitHub…"
git push origin "$BRANCH"
ok "Cod trimis ($(git rev-parse --short HEAD): $(git log -1 --format=%s))"

# 5. Pornește workflow-ul și așteaptă să apară rularea nouă
previous="$(gh run list --workflow "$WORKFLOW" --limit 1 --json databaseId -q '.[0].databaseId' 2>/dev/null || true)"

say "→ Pornesc deploy-ul…"
gh workflow run "$WORKFLOW" --ref "$BRANCH" -f maintenance="$MAINTENANCE" >/dev/null

run_id=""
for _ in $(seq 1 30); do
    sleep 2
    latest="$(gh run list --workflow "$WORKFLOW" --limit 1 --json databaseId -q '.[0].databaseId' 2>/dev/null || true)"
    if [ -n "$latest" ] && [ "$latest" != "${previous:-}" ]; then run_id="$latest"; break; fi
done
[ -n "$run_id" ] || fail "Rularea nu a apărut în GitHub. Verifică tab-ul Actions din repo."

url="$(gh run view "$run_id" --json url -q .url)"
printf '%s\n' "${dim}Rularea: $url${reset}"

# 6. Urmărește pașii live (dacă o altă rulare e în curs, aceasta așteaptă la coadă)
if gh run watch "$run_id" --interval 3 --exit-status; then
    echo
    ok "Deploy reușit."
    exit 0
fi

echo
printf '%s\n' "${red}✖ Deploy eșuat.${reset} Ultimele linii din pasul care a picat:" >&2
gh run view "$run_id" --log-failed 2>/dev/null | tail -30 >&2 || true
printf '%s\n' "${dim}Detalii complete: $url${reset}" >&2
exit 1
