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
$settings = [];
$settings['templates_dir'] = rex_request('templates_dir', 'string');
$settings['modules_dir'] = rex_request('modules_dir', 'string');
$settings['actions_dir'] = rex_request('actions_dir', 'string');
$settings['template_suffix'] = rex_request('template_suffix', 'string');
$settings['modules_in_suffix'] = rex_request('modules_in_suffix', 'string');
$settings['modules_out_suffix'] = rex_request('modules_out_suffix', 'string');
$settings['actions_preview_suffix'] = rex_request('actions_preview_suffix', 'string');
$settings['actions_presave_suffix'] = rex_request('actions_presave_suffix', 'string');
$settings['actions_postsave_suffix'] = rex_request('actions_postsave_suffix', 'string');
$frontendSync = rex_request('frontend_sync', 'bool');
$backendSync = rex_request('backend_sync', 'bool');

if ($func == 'update') {
    foreach ($settings as $key => $value) {
	$this->setConfig($key, $value);
    }
    $this->setConfig('frontend_sync', $frontendSync);
    $this->setConfig('backend_sync', $backendSync);
    echo rex_view::info($this->i18n('config_saved'));
}

$content = '
    <fieldset>
';
	$formElements = [];
	foreach ($settings as $key => $value) {
	    $n = [];
	    $n['label'] = '<label for="'.$key.'">' . rex_i18n::msg('label_'.$key) . '</label>';
	    $n['field'] = '<input class="form-control" id="'.$key.'" type="text" name="'.$key.'" value="' . htmlspecialchars($this->getConfig($key)) . '" >';
	    $formElements[] = $n;
	}
	
	$n = [];
	$n['field'] = '<input class="form-control" id="sync-update" type="hidden" name="func" value="update" >';
	$formElements[] = $n;
	
	$fragment = new rex_fragment();
	$fragment->setVar('flush', true);
	$fragment->setVar('group', true);
	$fragment->setVar('elements', $formElements, false);
	$content .= $fragment->parse('core/form/form.php');

	$formCheckboxes = [];
	$n = [];
	$n['label'] = '<label for="backend_sync">' . rex_i18n::msg('label_backend_sync') . '</label>';
	$n['field'] = '<input type="checkbox" id="backend_sync" name="backend_sync" value="1" ' . ($this->getConfig('backend_sync') ? 'checked="checked" ' : '') . '>';
	$formCheckboxes[] = $n;
	$n = [];
	$n['label'] = '<label for="frontend_sync">' . rex_i18n::msg('label_frontend_sync') . '</label>';
	$n['field'] = '<input type="checkbox" id="frontend_sync" name="frontend_sync" value="1" ' . ($this->getConfig('frontend_sync') ? 'checked="checked" ' : '') . '>';
	$formCheckboxes[] = $n;

	$fragment = new rex_fragment();
	$fragment->setVar('elements', $formCheckboxes, false);
	$content .= $fragment->parse('core/form/checkbox.php');
    
    $content .= '</fieldset>';

    $formElements = [];
    $n = [];
    $n['field'] = '<button class="btn btn-save" type="submit" name="func" value="update" ' . rex::getAccesskey(rex_i18n::msg('save_settings'), 'save') . '>' . rex_i18n::msg('save_settings') . '</button>';
    $formElements[] = $n;

    $fragment = new rex_fragment();
    $fragment->setVar('elements', $formElements, false);
    $buttons = $fragment->parse('core/form/submit.php');
    
    $fragment = new rex_fragment();
    $fragment->setVar('class', 'edit', false);
    $fragment->setVar('title', rex_i18n::msg('edit_settings'));
    $fragment->setVar('body', $content, false);
    $fragment->setVar('buttons', $buttons, false);
    $parsedContent = $fragment->parse('core/page/section.php');

    $form = '
        <form id="rex-form-sync-settings" action="' . rex_url::currentBackendPage() . '" method="post">
            ' . $parsedContent . '
        </form>';

    echo $form;
