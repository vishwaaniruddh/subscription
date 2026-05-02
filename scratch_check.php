<?php
require_once __DIR__ . '/vendor/autoload.php';
$db = App\Database::getInstance();

echo "=== subscription_history entries ===\n";
$rows = $db->query("SELECT * FROM subscription_history ORDER BY timestamp DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
if (empty($rows)) echo "  (empty)\n";
else foreach ($rows as $r) echo "  [{$r['timestamp']}] service:{$r['service_id']} action:{$r['action_type']} old:{$r['old_value']} new:{$r['new_value']}\n";
echo "Total: " . $db->query("SELECT COUNT(*) FROM subscription_history")->fetchColumn() . "\n";

echo "\n=== users in subscription ===\n";
$rows = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) echo "  [{$r['created_at']}] ID:{$r['id']} service:{$r['service_id']} user:{$r['user_identifier']} status:{$r['status']}\n";
