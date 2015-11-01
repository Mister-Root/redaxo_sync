<?php
/**
 * This file is part of the sync package.
 *
 * @author (c) Pascal Ruscher <pascal@dev-train.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$func = rex_request('func', 'string');

$content = '<h3>' . rex_i18n::msg('sync_start') . '</h3><p>' . rex_i18n::msg('sync_text') . '</p>';

if ($func == 'sync') {
    $sync = new Sync();
    $content .= '<ul>';
    foreach ($sync->getMessages() as $message) {
	$content .= '<li>'.$message.'</li>'; 
    }
    $content .= '</ul>';
}

$content .= '<p><a class="btn btn-setup" href="' . rex_url::currentBackendPage(['func' => 'sync']) . '" data-confirm="' . rex_i18n::msg('sync_confirm') . '?" data-pjax="false">' . rex_i18n::msg('sync') . '</a></p>';

$fragment = new rex_fragment();
$fragment->setVar('content', $content, false);
$content = $fragment->parse('core/page/grid.php');

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('sync_functions'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');