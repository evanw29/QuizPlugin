<?php
$new_key = sodium_crypto_secretbox_keygen();

$encoded_key_for_storage = sodium_bin2base64($new_key, SODIUM_BASE64_VARIANT_ORIGINAL);

echo $encoded_key_for_storage;
