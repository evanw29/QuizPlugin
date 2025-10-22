<?php
$new_key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

$encoded_key_for_storage = sodium_bin2base64($new_key, SODIUM_BASE64_VARIANT_ORIGINAL);

echo $encoded_key_for_storage;
