.PHONNY: check
check:
	MODIFIED_FILES=$$(git diff --name-only --diff-filter=ACMRTUXB | grep '\.php$$' | tr '\n' ' ') && php vendor/bin/php-cs-fixer fix $$MODIFIED_FILES --dry-run --diff --config=.php-cs-fixer.dist.php

.PHONNY: fix
fix:
	MODIFIED_FILES=$$(git diff --name-only --diff-filter=ACMRTUXB | grep '\.php$$' | tr '\n' ' ') && php vendor/bin/php-cs-fixer fix $$MODIFIED_FILES --config=.php-cs-fixer.dist.php
