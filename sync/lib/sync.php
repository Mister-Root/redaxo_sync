<?php

/**
 * Redaxo synchronisation script
 *
 * Author: Gregor Aisch / Dave Gööck / Christoph Mewes
 * Updated for Redaxo 5 and Addon Integration: Pascal Ruscher
 * SUMMARY
 *
 * synchronizes database-stored templates and modules with files
 *
 * INSTALL NOTES
 *
 * 	 you have to set a global variable in your Eclipse environment
 *   > window > preferences > run/debug > string substitution
 *   variable = "PHP_PATH", value = "path/to/your/php/installation/"
 *   variable = "PHP_INI", value = "path/to/your/php.ini"
 *   
 *   (Attention: WAMP uses wamp\Apache\bin\php.ini, not wamp\php\php.ini!)
 *   
 *   http://blog.webvariants.de/redaxo-templates-module-actions-mit-rexsync-synchronisieren
 */

use Symfony\Component\Yaml\Parser;

class Sync
{
    private static $verbose = false;
    private static $rebuild_cache = false;
    private static $metaInfosCache = [];
    private static $config = false;
    private static $sql = false;
    private static $patterns = false;

    // directories
    private static $redaxo_dir = '';
    private static $templates_dir = '';
    private static $modules_dir = '';
    private static $actions_dir = '';
    
    public static $output = [];
    
    public static function __contstruct()
    {
	self::load();
    }
    
    public static function load()
    {
	self::initialize();
	self::synchronize();
    }
    
    private static function initialize()
    {
	if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
	    // suppress errors in REDAXO
	    error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
	}
	$configFile = rex_path::data('config.yml');
	
	self::$patterns = rex_addon::get('sync')->getConfig();
	self::$redaxo_dir = rex_path::backend();
	self::setVariables();
	
	if (file_exists($configFile)) {
	    $yaml = new Parser();
	    self::$config = $yaml->parse(rex_file::get($configFile));
	    self::debug('config.yml successfully included', true);
	    self::openDBConnection();
	} else {
	    self::debug('couldn\'t find REDAXO\'s config.yml');
	    exit(-1);
	}
    }

    private static function synchronize()
    {
	self::$rebuild_cache = false; // article-cache will be regenerated in case of any changes

	self::synchronizeDir(self::$actions_dir, self::$patterns['actions_preview_suffix']);
	self::synchronizeDir(self::$actions_dir, self::$patterns['actions_presave_suffix']);
	self::synchronizeDir(self::$actions_dir, self::$patterns['actions_postsave_suffix']);
	self::synchronizeDir(self::$modules_dir, self::$patterns['modules_in_suffix']);
	self::synchronizeDir(self::$modules_dir, self::$patterns['modules_out_suffix']);
	self::synchronizeDir(self::$templates_dir, self::$patterns['template_suffix']);
	
	if (self::$rebuild_cache) {
	    self::clearRedaxoCache();
	}
	self::debug('rex_sync end');
    }
    
    public static function getMessages()
    {
	return self::$output;
    }
    
    public static function setTemplatesDir($path) {
	self::$templates_dir = rex_path::frontend($path);
    }
    public static function setModulesDir($path) {
	self::$modules_dir = rex_path::frontend($path);
    }
    public static function setActionsDir($path) {
	self::$actions_dir = rex_path::frontend($path);
    }

    private static function setVariables()
    {
	self::setActionsDir(self::$patterns['actions_dir']);
	self::setModulesDir(self::$patterns['modules_dir']);
	self::setTemplatesDir(self::$patterns['templates_dir']);
    }
    
    private static function debug($msg, $verboseOnly = false)
    {
	if (self::$verbose || !$verboseOnly) {
	    self::$output[] = $msg;
	}
    }

    private static function openDBConnection()
    {
	self::$sql = rex_sql::factory();
    }

    private static function synchronizeDir($dirname, $suffix)
    {
	$location = $dirname;
	if (is_dir($location)) {
	    $files = glob("$location/*$suffix");
	    foreach ($files as $file) {
		self::debug('found file ' . $file, true);
		self::processFile($location, $suffix, basename($file));
	    }
	} else {
	    self::debug($location . ' is not a directory');
	}
    }

    private static function processFile($location, $suffix, $filename)
    {
	$objectName = substr($filename, 0, -strlen($suffix));
	if (empty($objectName)) {
	    return;
	}
	$content = rex_file::get($location . $filename);
	if (empty($content)) {
	    return;
	}
	$type = self::getType($suffix);
	if (empty($type)) {
	    return;
	}
	$subtype = self::getSubType($suffix, $type);
	// try to get module id from meta-attributes
	$id = self::getMetaInfo($content, 'param', 'id');
	// try to get object-id from object-name
	if (is_null($id)) {
	    $id = self::findID($objectName, $type);
	}
	if (is_null($id)) {
	    self::debug('unable to identify input data from ' . $filename);
	    return;
	}
		
	// fetch data from db
	self::$sql->setQuery('SELECT * FROM ' . self::$config['table_prefix'] . $type . ' WHERE id = ' . $id);
	$contentField = self::getDBContentFieldName($type, $subtype);
		
	if (self::$sql->getRows()) {
	    if (!empty($contentField)) {
		if ($content != self::$sql->getValue($contentField)) {
		    self::updateData($type, $subtype, $content, $contentField, $objectName, $id);
		}
	    }
	} else {
	    self::insertData($type, $subtype, $content, $contentField, $objectName, $id);
	}
    }

    private static function updateData($type, $subtype, $content, $contentField, $objectName, $id)
    {
	$res = true;
	switch ($type) {
	    case 'action' :
		$res = self::updateAction($subtype, $content, $contentField, $objectName, $id);
		break;
	    case 'module' :
		$res = self::updateModule($subtype, $content, $contentField, $objectName, $id);
		break;
	    case 'template' :
		$res = self::updateTemplate($subtype, $content, $contentField, $objectName, $id);
		break;
	    default :
		break;
	}
	if (is_string($res)) {
	    self::debug('error while updating ' . $type . ' (' . $subtype . '): "' . $objectName . '":   ' . $res);
	} elseif (!$res) {
	    self::debug('error while updating ' . $type . ' (' . $subtype . '): "' . $objectName . '"');
	} else {
	    self::debug('updated ' . $type . ' (' . $subtype . '): "' . $objectName . '"');
	    self::$rebuild_cache = true;
	}
    }

    private static function updateModule($subtype, $content, $contentField, $objectName, $id)
    {
	$title = self::getMetaInfo($content, 'param', 'name');
	$actions = self::getMetaInfo($content, 'param', 'actions');
	$revision = self::getMetaInfo($content, 'param', 'rev');
	if (empty($title)) {
	    $title = $objectName;
	}
	ob_start();

	if (eval("return true; ?> $content") == false) {
	    $error = ob_get_clean();
	    $error = trim(str_replace("Parse error: syntax error, ", "", $error));
	    $error = preg_replace("/in .:[^:]+ : eval\\(\\)'d code/s", "in $objectName.module.php", $error);
	    return $error;
	}
	ob_end_clean();

	self::$sql->setTable(self::$config['table_prefix'] . 'module');
	self::$sql->setWhere('id = ' . intval($id));
	self::$sql->setValue($contentField, $content);
	if (!empty($title)) { self::$sql->setValue('name', trim($title)); }
	self::$sql->setValue('updateuser', 'admin');
	self::$sql->setValue('updatedate', date('Y-m-d H:i:s'));
	self::$sql->setValue('revision', $revision);
	
	try {
	    self::$sql->update();
	    self::updateModuleActions($actions, $id);
	    return true;
	} catch (rex_sql_exception $e) {
	    echo $e->getMessage();
	}
    }

    private static function updateModuleActions($actions, $id)
    {
	$oldActions = array();
	$actionsArray = self::$sql->getArray('SELECT action_id, id FROM ' . self::$config['table_prefix'] . 'module_action WHERE module_id = ' . $id . '');

	if (!empty($actionsArray)) {
	    foreach ($actionsArray as $action) {
		$oldActions[$action['action_id']] = $action['action_id'];
	    }
	}
	if (is_array($actions)) {
	    foreach ($actions as $action) {
		if (!isset($oldActions[$action])) {
		    self::$sql->setTable(self::$config['table_prefix'] . 'module_action');
		    self::$sql->setValue('module_id', intval($id));
		    self::$sql->setValue('action_id', intval($action));
		    try {
			self::$sql->insert();
			return true;
		    } catch (rex_sql_exception $e) {
			echo $e->getMessage();
		    }
		} else {
		    unset($oldActions[$action]);
		}
	    }
	}
	foreach ($oldActions as $action) {
	    self::$sql->setTable(self::$config['table_prefix'] . 'module_action');
	    self::$sql->setWhere('module_id = ' . intval($id) . ' AND action_id = ' . intval($action));
	    try {
		self::$sql->delete();
		return true;
	    } catch (rex_sql_exception $e) {
		echo $e->getMessage();
	    }
	}
    }

    private static function updateTemplate($subtype, $content, $contentField, $objectName, $id)
    {
	$title = self::getMetaInfo($content, 'param', 'name');
	$active = self::getMetaInfo($content, 'param', 'active');
	$revision = self::getMetaInfo($content, 'param', 'rev');
	if (empty($title)) {
	    $title = $objectName;
	}
	ob_start();

	if (eval("return true; ?> $content") == false) {
	    $error = ob_get_clean();
	    $error = trim(str_replace("Parse error: syntax error, ", "", $error));
	    $error = preg_replace("/in .:[^:]+ : eval\\(\\)'d code/s", "in $objectName.template.php", $error);
	    return $error;
	}
	ob_end_clean();
	
	self::$sql->setTable(self::$config['table_prefix'] . 'template');
	self::$sql->setWhere('id = ' . intval($id));
	self::$sql->setValue('name', trim($title));
	self::$sql->setValue($contentField, $content);
	self::$sql->setValue('updateuser', 'admin');
	self::$sql->setValue('updatedate', date('Y-m-d H:i:s'));
	if (isset($active)) { self::$sql->setValue('active', intval($active)); }
	self::$sql->setValue('revision', $revision);
	
	try {
	    self::$sql->update();
	    return true;
	} catch (rex_sql_exception $e) {
	    echo $e->getMessage();
	}
    }

    private static function updateAction($subtype, $content, $contentField, $objectName, $id)
    {
	$title = self::getMetaInfo($content, 'param', 'name');
	$add = intval(self::getMetaInfo($content, 'event', 'ADD'));
	$edit = intval(self::getMetaInfo($content, 'event', 'EDIT'));
	$delete = intval(self::getMetaInfo($content, 'event', 'DELETE'));
	$revision = self::getMetaInfo($content, 'param', 'rev');
	
	if (empty($title)) {
	    $title = $objectName;
	}
	$bitmask = ($add == 1 ? 1 : 0) + ($edit == 1 ? 2 : 0) + ($delete == 1 ? 4 : 0);
	
	self::$sql->setTable(self::$config['table_prefix'] . 'action');
	self::$sql->setWhere('id = ' . intval($id));
	if (!empty($title)) { self::$sql->setValue('name', trim($title)); }
	self::$sql->setValue($contentField, $content);
	self::$sql->setValue($contentField.'mode', $bitmask);
	self::$sql->setValue('updateuser', 'admin');
	self::$sql->setValue('updatedate', date('Y-m-d H:i:s'));
	self::$sql->setValue('revision', $revision);
	
	try {
	    self::$sql->update();
	    return true;
	} catch (rex_sql_exception $e) {
	    echo $e->getMessage();
	}
    }

    private static function insertData($type, $subtype, $content, $contentField, $objectName, $id)
    {
	$res = true;
	switch ($type) {
	    case 'action' :
		$res = self::insertAction($subtype, $content, $contentField, $objectName, $id);
		break;
	    case 'module' :
		$res = self::insertModule($subtype, $content, $contentField, $objectName, $id);
		break;
	    case 'template' :
		$res = self::insertTemplate($subtype, $content, $contentField, $objectName, $id);
		break;
	    default :
		break;
	}
	if (is_string($res)) {
	    self::debug('error while updating ' . $type . ' (' . $subtype . '): "' . $objectName . '":   ' . $res);
	} elseif (!$res) {
	    self::debug('error while updating ' . $type . ' (' . $subtype . '): "' . $objectName . '"');
	} else {
	    self::debug('created ' . $type . ' (' . $subtype . '): "' . $objectName . '"');
	    self::$rebuild_cache = true;
	}
    }

    private static function insertModule($subtype, $content, $contentField, $objectName, $id)
    {
	$title = self::getMetaInfo($content, 'param', 'name');
	$actions = self::getMetaInfo($content, 'param', 'actions');
	$revision = self::getMetaInfo($content, 'param', 'rev');
		
	if (empty($title)) {
	    $title = $objectName;
	}
	ob_start();
	if (eval("return true; ?> $content") == false) {
	    $error = ob_get_clean();
	    $error = trim(str_replace("Parse error: syntax error, ", "", $error));
	    $error = preg_replace("/in .:[^:]+ : eval\\(\\)'d code/s", "in $objectName.module.php", $error);
	    return $error;
	}
	ob_end_clean();
		
	self::$sql->setTable(self::$config['table_prefix'] . 'module');
	self::$sql->setValue('id', intval($id));
	self::$sql->setValue('name', trim($title));
	self::$sql->setValue($contentField, $content);
	self::$sql->setValue('createuser', 'admin');
	self::$sql->setValue('createdate', date('Y-m-d H:i:s'));
	self::$sql->setValue('revision', $revision);
	
	try {
	    self::$sql->insert();
	    self::updateModuleActions($actions, $id);
	    return true;
	} catch (rex_sql_exception $e) {
	    echo $e->getMessage();
	}
    }

    private static function insertTemplate($subtype, $content, $contentField, $objectName, $id)
    {
	$title = self::getMetaInfo($content, 'param', 'name');
	$active = self::getMetaInfo($content, 'param', 'active');
	$revision = self::getMetaInfo($content, 'param', 'rev');
	if (empty($title)) {
	    $title = $objectName;
	}
	ob_start();
	if (eval("return true; ?> $content") == false) {
	    $error = ob_get_clean();
	    $error = trim(str_replace("Parse error: syntax error, ", "", $error));
	    $error = preg_replace("/in .:[^:]+ : eval\\(\\)'d code/s", "in $objectName.template.php", $error);
	    return $error;
	}
	ob_end_clean();

	$attributesString = '{"ctype":[],"modules":{"1":{"all":"1"}},"categories":{"all":"1"}}';
	
	self::$sql->setTable(self::$config['table_prefix'] . 'template');
	self::$sql->setValue('id', trim($id));
	self::$sql->setValue('name', trim($title));
	self::$sql->setValue($contentField, $content);
	self::$sql->setValue('active', (isset($active) ? intval($active) : 0));
	self::$sql->setValue('createuser', 'admin');
	self::$sql->setValue('createdate', date('Y-m-d H:i:s'));
	self::$sql->setValue('attributes', $attributesString);
	self::$sql->setValue('revision', $revision);
	
	try {
	    self::$sql->insert();
	    return true;
	} catch (rex_sql_exception $e) {
	    echo $e->getMessage();
	}
    }

    private static function insertAction($subtype, $content, $contentField, $objectName, $id)
    {
	$title = self::getMetaInfo($content, 'param', 'name');
	$add = intval(self::getMetaInfo($content, 'event', 'ADD'));
	$edit = intval(self::getMetaInfo($content, 'event', 'EDIT'));
	$delete = intval(self::getMetaInfo($content, 'event', 'DELETE'));
	$revision = self::getMetaInfo($content, 'param', 'rev');

	if (empty($title)) {
	    $title = $objectName;
	}
	$bitmask = ($add == 1 ? 1 : 0) + ($edit == 1 ? 2 : 0) + ($delete == 1 ? 4 : 0);

	self::$sql->setTable(self::$config['table_prefix'] . 'action');
	self::$sql->setValue('id', intval($id));
	self::$sql->setValue('name', trim($title));
	self::$sql->setValue($contentField, $content);
	self::$sql->setValue($contentField.'mode', $bitmask);
	self::$sql->setValue('createuser', 'admin');
	self::$sql->setValue('createdate', date('Y-m-d H:i:s'));
	self::$sql->setValue('revision', $revision);
	
	try {
	    self::$sql->insert();
	    return true;
	} catch (rex_sql_exception $e) {
	    echo $e->getMessage();
	}
    }

    private static function getDBContentFieldName($type, $subtype)
    {
	if ($type == 'template') {
	    return 'content';
	}
	if ($type == 'action' || $type == 'module') {
	    if (!empty($subtype)) {
		return $subtype;
	    }
	}
	return null;
    }

    private static function findID($onjectName, $type)
    {
	if (empty($type)) {
	    return null;
	}
	$id = null;
	self::$sql->setQuery('SELECT id FROM ' . self::$config['table_prefix'] . $type . ' WHERE name = "' . $onjectName . '"');
	if (self::$sql->getRows()) {
	    $id = self::$sql->getValue('id');
	}
	return $id;
    }

    private static function getType($suffix)
    {
	$result = array();
	if (preg_match('/.*(template|module|action)\.php/', $suffix, $result) > 0) {
	    return $result[1];
	}
	return null;
    }

    private static function getSubType($suffix, $type)
    {
	$result = array();
	if (preg_match('/.*(postsave|presave|preview|input|output)\.' . $type . '.php/', $suffix, $result) > 0) {
	    return $result[1];
	}
	return null;
    }

    private static function clearRedaxoCache()
    {
	$path = rex_path::addonCache('structure');
	self::removeAllFiles($path, 'template');
	self::removeAllFiles($path, 'alist');
	self::removeAllFiles($path, 'clist');
	self::removeAllFiles($path, 'article');
	self::removeAllFiles($path, 'content');
	$templatePath = rex_path::addonCache('templates');
	self::removeAllFiles($templatePath, '*');

	self::debug('cleared templates and articles cache');
    }

    private static function removeAllFiles($path, $ext)
    {
	if (is_dir($path)) {
	    $files = glob("$path/*.$ext");
	    array_map('unlink', $files);
	}
    }

    private static function getMetaInfo($content, $token, $param)
    {
	$hash = md5($content);
	if (!isset(self::$metaInfosCache[$hash])) {
	    self::$metaInfosCache[$hash] = self::getAllMetaInfos($content);
	}
	if (isset(self::$metaInfosCache[$hash][$token][$param])) {
	    return self::$metaInfosCache[$hash][$token][$param];
	}
	return null;
    }

    private static function getAllMetaInfos($content)
    {
	static $regex = '/@rex_(\w+)\s+(\w+)\s+(.+)/';

	$infos = array();
	$lines = explode("\n", $content);

	foreach ($lines as $line) {
	    if (preg_match($regex, $line, $result) > 0) {
		if ($result[1] == 'attribute' || $result[2] == 'actions') {
		    eval('$r = ' . $result[3] . ';');
		} else {
		    $r = $result[3];
		}
		$infos[$result[1]][$result[2]] = $r;
	    }
	}
	return $infos;
    }
}
