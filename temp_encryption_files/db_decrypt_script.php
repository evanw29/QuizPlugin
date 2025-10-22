<?php
require_once 'wp-load.php';
$encryption_key_path = PRIVATE_KEY_FILE;

if (!file_exists($encryption_key_path)) {
    exit('Error: Key file not found at ' . $encryption_key_path);
}

$encryption_key_b64 = trim(file_get_contents($encryption_key_path));
$encryption_key = sodium_base642bin($encryption_key_b64, SODIUM_BASE64_VARIANT_ORIGINAL);

function decrypt_current_db(string $encrypted_field): string|false {
    global $encryption_key;
    $decoded_field = sodium_base642bin($encrypted_field, SODIUM_BASE64_VARIANT_ORIGINAL);
    $nonce = mb_substr($decoded_field, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
    $encrypted_text = mb_substr($decoded_field, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
    $field_text = sodium_crypto_secretbox_open($encrypted_text, $nonce, $encryption_key);

    sodium_memzero($nonce);

    return $field_text;
}

set_time_limit(0);

global $wpdb;
$table_name = "wp_quizUsers";
echo "Starting decryption process\n";

$entries = $wpdb->get_results("SELECT user_id, first_name, last_name, email, phone_number, gender, province FROM {$table_name} WHERE blind_index IS NOT NULL OR blind_index != ''");
if ($entries) {
    foreach ($entries as $entry) {
        // Save decrypted data
        $decrypted_first_name = decrypt_current_db((string)$entry->first_name);
        $decrypted_last_name = decrypt_current_db((string)$entry->last_name);
        $decrypted_email = decrypt_current_db((string)$entry->email);
        $decrypted_phone_number = decrypt_current_db((string)$entry->phone_number);
        $decrypted_gender = decrypt_current_db((string)$entry->gender);
        $decrypted_province = decrypt_current_db((string)$entry->province);
        $wpdb->update(
            $table_name,
            array(
                'first_name' => $decrypted_first_name,
                'last_name' => $decrypted_last_name,
                'email' => $decrypted_email,
                'phone_number' => $decrypted_phone_number,
                'gender' => $decrypted_gender,
                'province' => $decrypted_province,
                'blind_index' => ''
            ),
            array('user_id' => $entry->user_id)
        );
        echo "\nDecrypted user_id: " . $entry->user_id;
    }
}

sodium_memzero($encryption_key);
echo "\nDecryption complete\n";