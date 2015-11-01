<?php
/**
 * This file is part of the sync package.
 *
 * @author (c) Pascal Ruscher <pascal@dev-train.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$settings = [];
$settings['templates_dir'] = 'develop/templates/';
$settings['modules_dir'] = 'develop/modules/';
$settings['actions_dir'] = 'develop/actions/';
$settings['template_suffix'] = '.template.php';
$settings['modules_in_suffix'] = '.input.module.php';
$settings['modules_out_suffix'] = '.output.module.php';
$settings['actions_preview_suffix'] = '.preview.action.php';
$settings['actions_presave_suffix'] = '.presave.action.php';
$settings['actions_postsave_suffix'] = '.postsave.action.php';
$settings['backend_sync'] = 1;
$settings['frontend_sync'] = 1;

foreach ($settings as $key => $value) {
    $this->setConfig($key, $value);
}