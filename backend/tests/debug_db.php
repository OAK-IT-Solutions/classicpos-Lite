<?php
try {
    \ = DB::connection('pgsql')->getPdo();
    echo "PGSQL connected\n";
    \ = \->query("SELECT pg_current_xact_id_if_assigned() IS NOT NULL as in_txn");
    \ = \->fetch(PDO::FETCH_ASSOC);
    echo "PGSQL in txn: " . (\['in_txn'] ? 'yes' : 'no') . "\n";
    \ = \->query("SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_name = 'agent_referrals') as exists_check");
    \ = \->fetch(PDO::FETCH_ASSOC);
    echo "agent_referrals exists: " . (\['exists_check'] ? 'yes' : 'no') . "\n";
} catch (Exception \) {
    echo "Error: " . \->getMessage() . "\n";
}
