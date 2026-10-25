<?php
// Run from terminal: php create_hashes.php
// Copy the generated hashes into the INSERT statements described in README.md.
echo "Staff password hash:\n";
echo password_hash('ChangeThisStaffPassword!', PASSWORD_DEFAULT) . "\n\n";
echo "Recipe PIN hash:\n";
echo password_hash('4826', PASSWORD_DEFAULT) . "\n";
