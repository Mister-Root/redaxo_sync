<?php
/**
 * This file is part of the sync package.
 *
 * @author (c) Pascal Ruscher <pascal@dev-train.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$subpage = rex_be_controller::getCurrentPagePart(2);
$func = rex_request('func', 'string');

echo rex_view::title(rex_i18n::msg('sync_title'));

include rex_be_controller::getCurrentPageObject()->getSubPath();