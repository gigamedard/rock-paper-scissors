#!/bin/sh
# Headless E2E — vérifie l'API, le build front (P3), la DB, les commandes, l'outbox et les tests unitaires.
# Exécution : sh tests/headless_e2e.sh   (sur le VPS, services BattlePool actifs)
BASE="${BASE:-http://127.0.0.1:8001}"
PASS=0; FAIL=0
ok(){ PASS=$((PASS+1)); echo "PASS  $1"; }
ko(){ FAIL=$((FAIL+1)); echo "FAIL  $1  (${2:-})"; }
ck(){ if [ "$2" = "$3" ]; then ok "$1"; else ko "$1" "got=$2 want=$3"; fi }

DBQ(){ docker exec battlepool-db sh -c "mysql -u root -p\"\$(cat /run/secrets/mysql_password)\" rock_paper_scissors -N -e \"$1\"" 2>/dev/null; }
JSON(){ python3 -c "import sys,json;d=json.load(sys.stdin);print($1)" 2>/dev/null; }

echo "=== 1. Health API ==="
if curl -sf "$BASE/api/health" | grep -q '"status":"OK"'; then ok "health OK"; else ko "health"; fi

echo "=== 2. Artefacts (bankroll/P3) ==="
A=$(curl -s "$BASE/api/artefacts")
ck "bankroll_enabled"        "$(echo "$A" | JSON "str(d.get('bankroll_enabled')).lower()")" "true"
ck "session_target_q=1.2"    "$(echo "$A" | JSON "d.get('bankroll_session_target_q')")" "1.2"
ck "fee_rate=0.04"           "$(echo "$A" | JSON "d.get('bankroll_fee_rate')")" "0.04"
ck "refund_pct=1"            "$(echo "$A" | JSON "d.get('bankroll_refund_pct')")" "1"
ck "contract_fee=4"          "$(echo "$A" | JSON "d.get('smart_contract_fee_percentage')")" "4"
X=$(echo "$A" | JSON "round(float(d.get('bankroll_recovery_factor',0)),2)")
ck "recovery_factor~6.24"    "$X" "6.24"

echo "=== 3. Front (index + bundle P3) ==="
if curl -s "$BASE/" | grep -q "p3-breakdown"; then ok "index contient p3-breakdown"; else ko "index p3-breakdown"; fi
if curl -s "$BASE/" | grep -q "Budget session (C)"; then ok "index contient 'Budget session (C)'"; else ko "index label C"; fi
if curl -s "$BASE/" | grep -q 'value="1.05"'; then ok "index propose target 1.05"; else ko "index target 1.05"; fi
if curl -s "$BASE/" | grep -q 'value="1.2"'; then ok "index propose target 1.2"; else ko "index target 1.2"; fi
JS=$(curl -s "$BASE/build/assets/app.js")
if echo "$JS" | grep -q "p3-breakdown"; then ok "bundle contient la logique P3"; else ko "bundle P3"; fi
if echo "$JS" | grep -q "bankroll_recovery_factor"; then ok "bundle lit bankroll_recovery_factor"; else ko "bundle factor"; fi

echo "=== 4. Tables DB ==="
for t in side_effect_outbox refund_queue refund_ledger refund_fund_state; do
  c=$(DBQ "SHOW TABLES LIKE '$t'" | wc -l | tr -d ' ')
  ck "table $t" "$c" "1"
done

echo "=== 5. Commandes artisan ==="
CMDS=$(docker exec battlepool-app php artisan list 2>/dev/null)
for c in "outbox:process" "refund:process" "sim:memory" "game:engine"; do
  if echo "$CMDS" | grep -q "$c"; then ok "commande $c"; else ko "commande $c"; fi
done

echo "=== 6. Outbox E2E (transactionnel) ==="
PW=$(docker exec battlepool-app cat /run/secrets/db_app_password)
OUT=$(docker exec -e DB_PASSWORD="$PW" battlepool-app php artisan tinker --execute='use Illuminate\Support\Facades\DB; DB::beginTransaction(); $o=app(App\Services\SideEffectOutbox::class); $id=$o->push(1,"notification",["type"=>"TEST","data"=>["x"=>1]]); for($i=0;$i<10;$i++){ $o->processBatch(1000); $st=DB::table("side_effect_outbox")->where("id",$id)->value("status"); if($st==="done") break; } echo (string)$st; DB::rollBack();' 2>/dev/null | tail -1 | tr -d ' ')
ck "outbox push+process -> done" "$OUT" "done"

echo "=== 7. Tests unitaires (dans l'image) ==="
docker exec battlepool-app php tests/bankroll_state_machine_test.php >/tmp/ut1.log 2>&1; ck "bankroll_state_machine_test" "$?" "0"
docker exec battlepool-app php tests/refund_queue_simulation.php >/tmp/ut2.log 2>&1; ck "refund_queue_simulation" "$?" "0"

echo "======================================="
echo "RÉSULTAT : $PASS OK, $FAIL échec(s)"
[ "$FAIL" -eq 0 ] || exit 1
