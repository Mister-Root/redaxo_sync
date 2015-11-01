<h1>Sync Addon für Redaxo 5</h1>
<h2>Beschreibung</h2>
<p>Module und Templates können mit diesem Addon über das Dateisystem eingespielt werden.</p>
<p>Bei jedem Neuladen des Redaxo Backends, werden die Dateien in die Datenbank übernommen.</p>
<h2>Schnellstart</h2>
<p>Folgende Struktur wird im Root Verzeichnis angelegt:</p>
<pre>
/develop
  /actions
  /modules
  /templates
</pre>
<h3>Templates</h3>
<p>Bennung der Template Dateien:</p>
<pre>*.template.php</pre>
<p>Kopf der Template Dateien:</p>
<pre>
&lt;?php
/**
 * @rex_param      id		1
 * @rex_param      name		Templatename
 * @rex_param      active	1
 * @rex_param      rev		1.0
 */
?&gt;
</pre>
<p>Parameter:</p>
<p>
    @rex_param id: Eindeutige <b>ID</b><br>
    @rex_param name: Eindeutiger <b>Name</b><br>
    @rex_param active: <b>1</b> (aktiv) oder <b>0</b> (inaktiv)<br>
    @rex_param rev: Beliebige Revisionsbezeichnung
</p>
<h3>Module</h3>
<p>Bennung der Modul Dateien:</p>
<pre>
*.input.module.php
*.output.module.php
</pre>
<p>Kopf der Modul Dateien:</p>
<pre>
&lt;?php
/**
 * @rex_param	id	    1
 * @rex_param	name    Modulname
 * @rex_param	rev	    1.0
 */
?&gt;
</pre>
<p>Parameter:</p>
<p>
    @rex_param id: Eindeutige <b>ID</b><br>
    @rex_param name: Eindeutiger <b>Name</b><br>
    @rex_param active: <b>1</b> (aktiv) oder <b>0</b> (inaktiv)<br>
    @rex_param rev: Beliebige Revisionsbezeichnung
</p>
<h3>Aktionen</h3>
<p>Bennung der Aktion Dateien:</p>
<pre>
*.postsave.action.php
*.presave.action.php
*.preview.action.php
</pre>
<p>Kopf der Aktion Dateien:</p>
<pre>
&lt;?php
/**
 * @rex_param	id	    1
 * @rex_param	name    Testaktion
 * @rex_param	rev	    1.0
 * @rex_event	ADD	    1
 * @rex_event	EDIT    1
 */
?&gt;
</pre>
<p>Parameter:</p>
<p>
    @rex_param id: Eindeutige <b>ID</b><br>
    @rex_param name: Eindeutiger <b>Name</b><br>
    @rex_param active: <b>1</b> (aktiv) oder <b>0</b> (inaktiv)<br>
    @rex_param rev: Beliebige Revisionsbezeichnung
</p>
<h2>Benutzerdefinierte Konfiguration</h2>
<p>Es ist möglich im <b>Addon Sync</b> unter <b>Einstellungen</b> die <b>Suffixe</b> für die Benennung und die <b>Ordner</b> für den Sync festzulegen.</p>
