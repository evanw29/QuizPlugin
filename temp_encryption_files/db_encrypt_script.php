<?php
require_once 'wp-load.php';
$encryption_key_path = PRIVATE_KEY_FILE;
$blind_index_key_path = BLIND_INDEX_KEY_FILE;
if (!file_exists($encryption_key_path)) {
    exit('Error: Key file not found at ' . $encryption_key_path);
}
if (!file_exists($blind_index_key_path)) {
    exit('Error: Blind index key file not found at ' . $blind_index_key_path);
}

$encryption_key_b64 = trim(file_get_contents($encryption_key_path));
$encryption_key = sodium_base642bin($encryption_key_b64, SODIUM_BASE64_VARIANT_ORIGINAL);

$blind_index_key_b64 = trim(file_get_contents($blind_index_key_path));
$blind_index_key = sodium_base642bin($blind_index_key_b64, SODIUM_BASE64_VARIANT_ORIGINAL);

function current_db_blind_index(string $email, string $last_name, string $phone_number): string|false {
    global $blind_index_key;
    $combined = $email . $last_name . $phone_number;

    $blind_index = hash_hmac('sha256', $combined, $blind_index_key, true);
    $blind_index_b64 = sodium_bin2base64($blind_index, SODIUM_BASE64_VARIANT_ORIGINAL);

    sodium_memzero($combined);
    sodium_memzero($email);
    sodium_memzero($last_name);
    sodium_memzero($phone_number);

    return $blind_index_b64;
}

function encrypt_current_db(string $field): string|false {
    global $encryption_key;
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $encrypted = sodium_crypto_secretbox($field, $nonce, $encryption_key);
    $result = sodium_bin2base64($nonce . $encrypted, SODIUM_BASE64_VARIANT_ORIGINAL);

    sodium_memzero($nonce);
    sodium_memzero($field);
    return $result;
}

set_time_limit(0);

global $wpdb;
$table_name = "wp_quizUsers";
echo "Starting encryption process\n";

$entries = $wpdb->get_results("SELECT user_id, first_name, last_name, email, phone_number, gender, province FROM {$table_name} WHERE blind_index IS NULL OR blind_index = ''");
if ($entries) {
    foreach ($entries as $entry) {
        // Save blind index
        $current_entry_blind_index = current_db_blind_index((string)$entry->email, (string)$entry->last_name, (string)$entry->phone_number);

        // Save encrypted data
        $encrypted_first_name = encrypt_current_db((string)$entry->first_name);
        $encrypted_last_name = encrypt_current_db((string)$entry->last_name);
        $encrypted_email = encrypt_current_db((string)$entry->email);
        $encrypted_phone_number = encrypt_current_db((string)$entry->phone_number);
        $encrypted_gender = encrypt_current_db((string)$entry->gender);
        $encrypted_province = encrypt_current_db((string)$entry->province);
        $wpdb->update(
            $table_name,
            array(
                'first_name' => $encrypted_first_name,
                'last_name' => $encrypted_last_name,
                'email' => $encrypted_email,
                'phone_number' => $encrypted_phone_number,
                'gender' => $encrypted_gender,
                'province' => $encrypted_province,
                'blind_index' => $current_entry_blind_index
            ),
            array('user_id' => $entry->user_id)
        );
	echo "\nEncrypted user_id: " . $entry->user_id;
    }
}

sodium_memzero($encryption_key);
sodium_memzero($blind_index_key);
echo "\nEncryption complete\n";