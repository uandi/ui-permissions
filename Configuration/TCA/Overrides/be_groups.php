<?php

declare(strict_types=1);

defined('TYPO3') || exit;

(static function (): void {
    // Allow more than 20 subgroups
    $GLOBALS['TCA']['be_groups']['columns']['subgroup']['config']['maxitems'] = 9999;
})();
