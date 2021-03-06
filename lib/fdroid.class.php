<?php
require_once('xmlconv.class.php');

/** Handle F-Droid repositories
 * @class fdroid extends xmlconv
 * @author Andreas Itzchak Rehberg
 * @copyright © 2016 by Andreas Itzchak Rehberg, protected under the GPLv2
 * @webpage https://github.com/IzzySoft/prepaf
 */
class fdroid extends xmlconv {

  /** directory the repo's files reside in
   * @property protected string repoDir
   */
  protected $repoDir;

  /** Whether to index for full-text-search.
   *  As this requires quite a big index (for larger repos), it's off by default.
   *  use self::setFTS(bool) to switch.
   * @property protected ftsEnabled
   */
  protected $ftsEnabled = false;
  /** Index for Full Text Search (FTS).
   *  This is an array of app_key=>app_json
   * @attribute protected ftsIndex
   */
  protected $ftsIndex = [];

  /** The repo data as object
   * @attribute protected object data
   */
  protected $data;

  /** Available/used categories
   * @attribute protected array cats
   */
  protected $cats = [];
  /** Available/used licenses
   * @attribute protected array licenses
   */
  protected $licenses = [];

  /** package_names and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute appIds
   */
  protected $appIds = [];
  /** app_names and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appNames
   */
  protected $appNames = [];
  /** app_lastbuild and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appBuilds
   */
  protected $appBuilds = [];
  /** app_repoAdded and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appAddeds
   */
  protected $appAddeds = [];
  /** app_repoUpdate and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appUpdates
   */
  protected $appUpdates = [];
  /** app_authors and their index key in data->application.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected autNames
   */
  protected $autNames = [];
  /** licenses with the index keys of apps (in data->application) being in them.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appLicenses
   */
  protected $appLicenses = [];
  /** categories with the index keys of apps (in data->application) being in them.
   *  This is an empty array unless explicitly filled by self::index
   * @attribute protected appCats
   */
  protected $appCats = [];

  /** How many apps we have.
   *  This is basically a shortcut to self::data->repo->{'@attributes'}->appcount
   * @attribute protected int appcount
   */
  protected $appcount;

  /** number of hits if we hadn't limited search results
   * @attribute protected int fullHits
   */
  protected $fullHits = 0;

  /** Default limit for pager (how many entries to return at maximum)
   * @attribute protected int limit
   */
  protected $limit = 0;

  /** Do we only have the XML/JSON file of some remote repo here – or a complete local repo?
   *  This will be determined on the existence of the metadata directory.
   * @attribute protexted bool index_only
   */
  protected $index_only = false;

  /** Constructor: Load the repo
   * @constructor fdroid
   * @param string path the repository's dir (where the index.xml resides) or the full path of the XML/JSON file itself
   * @param optional int limit how many apps to return. This sets the default used by the apps.
   * @param optional string catfile full path to categories.txt. If not given, the list won't support categories.
   */
  public function __construct($path, $limit=0, $catfile=null) {
    if ( is_dir($path) ) {
      $this->repoDir = $path;
      $file = $path.'/index.xml';
    } elseif ( is_file($path) ) {
      $file = $path;
      $this->repoDir = dirname($path);
    } else {
      trigger_error($path." is not a valid file or directory. Cannot initialize repo.",E_USER_ERROR);
      return;
    }
    ( is_dir(dirname($this->repoDir).'/metadata') ) ? $this->index_only = false : $this->index_only = true;
    switch ( pathinfo($file, PATHINFO_EXTENSION) ) {
      case 'xml': $this->loadXml($file); break;
      case 'json': $this->loadJSON($file); break;
      case 'jar':  // pathinfo($file, PATHINFO_BASENAME) == index-v1.jar -- oder in loadJSON im ZIP auf index-v1.json prüfen
      default: trigger_error("Specified index file '${file}' not supported. Cannot initialize repo.",E_USER_ERROR);
    }
    if (!$catfile) $catfile = $this->repoDir.'/categories.txt';
    if ( file_exists($catfile) ) {
      $this->cats = explode("\n",trim(file_get_contents($catfile)));
      sort($this->cats);
    }
    $this->data->repo->appcount = count($this->data->application);
    $this->appcount = $this->data->repo->appcount;
    $this->fullHits = $this->appcount;
    $this->setLimit($limit);
  }

  /** Override auto-detection on whether this is a full local repo
   * @method public setIndexOnly
   * @param in bool bool Do we just have the XML/JSON of some (remote) repo (true), or all APKs in the same place as well (false)?
   * @see self::index_only
   */
  public function setIndexOnly($bool) {
    $this->index_only = (bool) $bool;
  }

  /** Check the current setting of index_only
   * @method getIndexOnly
   * @return bool index_only
   * @see self::setIndexOnly
   * @see self::index_only
   */
  public function getIndexOnly() {
    return $this->index_only;
  }


  /** Override auto-detection on whether this is a full local repo
   * @method public setXmlOnly
   * @param in bool bool Do we just have the XML of some (remote) repo (true), or all APKs in the same place as well (false)?
   * @see self::index_only self::setIndexOnly
   * @deprecated use setIndexOnly instead
   */
  public function setXmlOnly($bool) {
    $this->setIndexOnly($bool);
  }

  /** Check the current setting of xml_only
   * @method getXmlOnly
   * @return bool xml_only
   * @see self::setIndexOnly
   * @see self::index_only
   * @deprecated use getIndexOnly instead
   */
  public function getXmlOnly() {
    return $this->getIndexOnly();
  }

  /** Load repository data from index.xml
   * @method protected loadXml
   * @param in string file full file name (with path) of the index.xml to load
   */
  protected function loadXml($file) {
    $this->data = $this->xml2obj($file);
    $this->data->repo->{'@attributes'}->description = $this->data->repo->description;
    $this->data->repo = $this->data->repo->{'@attributes'};
  }

  /** Set property depending if another property exist in a reference object (helper to loadJSON)
   * @method protected _setPropConditional
   * @param ref object obj     target object
   * @param string name     target property
   * @param object ref      reference object
   * @param string refname  reference property
   * @param optional mixed default   default if reference property is not set (null for optional properties)
   */
  protected function _setPropConditional(&$obj,$name,$ref,$refname,$default='') {
    if ( property_exists($ref,$refname) ) $obj->{$name} = $ref->{$refname};
    elseif ($default!==null) $obj->{$name} = $default;
  }

  /** Load repository data from index-v1.json (and convert to "old format")
   * @method protected loadJSON
   * @param in string file full file name (with path) of the index-v1.json to load
   */
  protected function loadJSON($file) {
    $data = json_decode( file_get_contents($file) );
    $this->data = new stdClass();
    $this->data->repo = $data->repo;
    $this->data->repo->url = &$this->data->repo->address;
    $this->data->repo->pubkey = '';
    $this->data->repo->timestamp = $this->data->repo->timestamp/1000;

    $this->data->application = [];
    foreach ( $data->apps as $app ) {
      $o = new stdClass();
      $o->id = $app->packageName;
      $o->added = date('Y-m-d',$app->added/1000);
      $o->lastupdated = date('Y-m-d',$app->lastUpdated/1000);
      foreach(['name','summary','icon','license','changelog'] as $f) {
        if (property_exists($app,$f)) $o->{$f} = $app->{$f};
        else $o->{$f} = '';
      }
      $this->_setPropConditional($o,'desc',$app,'description');
      $this->_setPropConditional($o,'source',$app,'sourceCode');
      $this->_setPropConditional($o,'tracker',$app,'issueTracker');
      $this->_setPropConditional($o,'web',$app,'webSite');
      $this->_setPropConditional($o,'author',$app,'authorName',null);
      $this->_setPropConditional($o,'email',$app,'authorEmail',null);
      if (property_exists($app,'antiFeatures')) $o->antifeatures = implode(',',$app->antiFeatures);
      if (property_exists($app,'categories')) {
        $o->categories = implode(',',$app->categories);
        $o->category = $app->categories[0];
      }
      $this->_setPropConditional($o,'donate',$app,'donate',null);
      $this->_setPropConditional($o,'flattr',$app,'flattrID',null);
      $this->_setPropConditional($o,'liberapay',$app,'liberapayID',null);
      $this->_setPropConditional($o,'bitcoin',$app,'bitcoin',null);
      $this->_setPropConditional($o,'litecoin',$app,'litecoin',null);

      // properties new with JSON which didn't exist before: we take this 1:1 as no backwards compatibility exists anyway
      $this->_setPropConditional($o,'suggestedVersionCode',$app,'suggestedVersionCode',null);
      $this->_setPropConditional($o,'localized',$app,'localized',null);

      // packages
      if ( property_exists($data->packages,$o->id) ) { // there should be at least one, but who knows?
        $ps = [];
        foreach ( $data->packages->{$o->id} as $pkg ) {
          $p = new stdClass();
          $p->version = $pkg->versionName;
          $p->versioncode = $pkg->versionCode;
          $p->apkname = $pkg->apkName;
          foreach(['hash','size','sig'] as $f) $p->{$f} = $pkg->{$f};
          $p->sdkver = $pkg->minSdkVersion;
          $this->_setPropConditional($p,'targetSdkVersion',$pkg,'targetSdkVersion',null);
          // JSON only: signer
          $p->added = date('Y-m-d',$pkg->added/1000);
          $perms = ''; if ( property_exists($pkg,'uses-permission') ) {
            foreach ($pkg->{'uses-permission'} as $perm) $perms .= ','.$perm[0];
            if (strlen($perms)) $perms = substr($perms,1);
          }
          $p->permissions = $perms;
          $ps[] = $p;
        }
        if ( count($ps) == 1 ) $o->package = $ps[0];
        else $o->package = $ps;
      }

      $this->data->application[] = $o;
    }
  }

  /** Set the default pager limit
   * @method public setLimit
   * @param opt int limit value to set the limit to (default: 0, i.e. no limit)
   * @see limit
   */
  public function setLimit($limit=0) {
    (int) $limit;
    if ( is_int($limit) ) $this->limit = abs($limit);
  }

  /** Get the default pager limit
   * @method public getLimit
   * @see limit
   */
  public function getLimit($limit=0) {
    return $this->limit;
  }

  /** Enable/Disable index for Full Text Search.
   *  Quite a big index (with the entire apps data as JSON), so you might wish
   *  to think twice if you're running a larger repo :)
   * @method setFTS
   * @param bool value TRUE to activate, FALSE to deactivate
   */
  public function setFTS($val) {
    $this->ftsEnabled = (bool) $val;
  }

  /** Get the repository meta data
   * @method getMeta
   * @return object metaData. properties: strings icon (fileName), name, pubkey, timestamp, url, version, int appcount, str description
   */
  public function getMeta() {
    $meta = $this->data->repo;
    $meta->description = $this->data->repo->description;
    return $meta;
  }

  /** Get used categories
   * @method getCats
   * @return array cats array[0..n] of string, alphabetically sorted
   */
  public function getCats() {
    return $this->cats;
  }

  /** Get used licenses
   * @method getLicenses
   * @return array licenses array[0..n] of string, alphabetically sorted
   */
  public function getLicenses() {
    return $this->licenses;
  }

  /** add some indexes on the app list for easier access
   * @method index
   */
  public function index() {
    foreach($this->data->application as $key=>$app) {
      unset($this->data->application[$key]->{'@attributes'}); // obsolete, only id
      $this->appIds[$app->id] = $key;
      if ( !is_string($app->name) ) { // buggy APK MetaData work-around
        $app->name = $app->id;
        // $this->data->application[$key]->name = $app->id; // done implicitly with previous line
      }
      $this->appNames[$app->name] = $key;
      if ( !property_exists($app,'categories') ) trigger_error('Category missing for '.$app->id,E_USER_NOTICE);
      else foreach(explode(',',$app->categories) as $ac) {
        if ( !isset($this->appCats[$ac])) $this->appCats[$ac] = [];
        $this->appCats[$ac][] = $key;
      }
      if ( isset($app->author) ) {
        if ( !isset($this->autNames[$app->author]) ) $this->autNames[$app->author] = [];
        $this->autNames[$app->author][] = $key;
      }
      if ( isset($app->license) ) {
        if ( !isset($this->appLicenses[$app->license]) ) $this->appLicenses[$app->license] = [];
        $this->appLicenses[$app->license][] = $key;
        $this->licenses[] = $app->license;
      }
      // now walk get the last modification (i.e. "app update") of the newest file
      if ( $this->index_only ) $this->data->application[$key]->lastbuild = null;
      elseif ( is_array($app->package) ) {
        for($i=0;$i<count($app->package);++$i) $this->data->application[$key]->package[$i]->built = date('Y-m-d',filemtime($this->repoDir.'/'.$app->package[$i]->apkname));
        $this->data->application[$key]->lastbuild = $app->package[0]->built;
      } else {
        $this->data->application[$key]->lastbuild = date('Y-m-d',filemtime($this->repoDir.'/'.$app->package->apkname));
        $this->data->application[$key]->package->built = $this->data->application[$key]->lastbuild;
      }
      if ( $app->lastupdated < $this->data->application[$key]->lastbuild ) $app->lastupdated = $this->data->application[$key]->lastbuild; // APK was replaced
      if ( !isset($this->appBuilds[$app->lastbuild]) ) $this->appBuilds[$app->lastbuild] = [];
      $this->appBuilds[$app->lastbuild][] = $key;
      if ( !$this->index_only ) {
        if ( file_exists(dirname($this->repoDir).'/metadata/'.$app->id.'.yml') ) $metafile = dirname($this->repoDir).'/metadata/'.$app->id.'.yml';
        elseif ( file_exists(dirname($this->repoDir).'/metadata/'.$app->id.'.txt') ) $metafile = dirname($this->repoDir).'/metadata/'.$app->id.'.txt';
        else $metafile = '';
        if ( !empty($metafile) ) { // get AppAdded and RequiresRoot from repodata:
          if ( preg_match('!^\s*AppAdded:\s?(\d{4}-\d{2}-\d{2})!ms',file_get_contents($metafile),$match) ) {
            $app->added = $match[1];
          } else {
            $metadate = date('Y-m-d',filemtime($metafile));
            if ( $metadate < $app->added ) $app->added = $metadate;
          } // Requires Root:
          if ( preg_match("!^Requires ?Root:( ')?yes'?$!ims",file_get_contents($metafile),$match) ) {
            $app->requirements = 'root';
          }
        }
      }
      if ( !isset($this->appAddeds[$app->added]) ) $this->appAddeds[$app->added] = [];
      $this->appAddeds[$app->added][] = $key;
      if ( !isset($this->appUpdates[$app->added]) ) $this->appUpdates[$app->added] = [];
      $this->appUpdates[$app->lastupdated][] = $key;
      if ( !property_exists($app,'summary') || !is_string($app->summary) ) $app->summary = ''; // work around bug in F-Droid's own repo XML
      if ( !property_exists($app,'desc')    || !is_string($app->desc) )    $app->desc    = '';
      if ( !property_exists($app,'summary') ) $app->summary = ''; // work around bug in F-Droid's own repo XML
      if ( $this->ftsEnabled ) {
        $this->ftsIndex[$key] = strtolower($app->name.$app->summary.strip_tags($app->desc));
      }
    }
    krsort($this->appBuilds); // newest first
    $this->licenses = array_unique($this->licenses);
    sort($this->licenses);
  }

  /** Get number of results regardless of $limit used
   * @method getFullHits
   * @return int fullHits number of available results to the last query if no $limit applied
   */
  function getFullHits() {
    return $this->fullHits;
  }

  /** Get the entire applist as-is (i.e. ordered by names)
   * @method getAppList
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @param optional str order field to order the list by. Permitted values: name,added, updated. Default: name.
   * @return array apps array[0..n] of object app
   * @verbatim
   *   each app with:  id (package_name), added (date), lastupdated (date), name (app_name), summary, icon, desc, license,
   *                   categories (CSV), category (the first one), web + source + tracker + changelog (URLs),
   *                   marketversion (object), marketvercode, antifeatures, requirements ("root"), package (object OR array[0..n] of objects)
   *                   lastbuild (YYYY-MM-DD; only when indexed) [, localized]
   *                   → added, lastupdated: relates to the repo
   *   package object: version, versioncode, apkname (fileName), hash, sig, size (bytes), sdkver, added (date),
   *                   permissions (CSV), nativecode (CSV), features (CSV)
   *                   → added, lastupdated: relates to the repo – no details of the file date (need to take that from files)
   *   localized object: only available with JSON index, and even there optional. Properties again are objects, going by the
   *                   names of their resp. languages (e.g. 'en-US', 'de-DE' or even simply 'fr'). Can hold summary, description,
   *                   featureGraphic, phoneScreenshots and more. The 'localized' object is taken 1:1 from the json_decode()d index.
   *   NOTE: If an app in a JSON index has localized summary/description, the global summary/description are usually empty.
   */
  function getAppList($start=0,$limit=null,$order='name') {
    $order = strtolower($order);
    if ( !in_array($order,['name','added','updated']) ) $order = 'name';
    $this->fullHits = $this->appcount;
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 && $start==0 ) { $apps = $this->data->application; goto sorter; } // get all
    if ( $limit==0 ) $max = $this->appcount;
    else $max = $start + $limit;
    $apps = [];
    for ($i=$start;$i<=$max;++$i) {
      if ( $i==$max || $i==$this->appcount ) break;
      $apps[] = $this->data->application[$i];
    }
    // sorting, if specified:
    sorter:
    if ( $order=='name' ) return $apps; // nothing to sort
    switch($order) {
      case 'added'  : $orderby = 'added'; break;
      case 'updated': $orderby = 'lastupdated'; break;
      case 'name':
      default: $orderby = 'name'; break; // just in case
    }
    $sorter = []; $sorted = [];
    foreach ($apps as $app) {
      $sorted[] = ['id'=>$app->id, $orderby=>$app->$orderby, 'app'=>$app];
      $sorter[$app->id] = $app->$orderby;
    }
    array_multisort($sorter,SORT_ASC,$sorted);
    $apps = []; // reset
    foreach($sorted as $s) $apps[] = $s['app'];
    return $apps;
  }

  /** Get all apps of a given category
   * @method getAppsByCat
   * @param str cat category
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  function getAppsByCat($cat,$start=0,$limit=null) {
    $this->fullHits = count($this->appCats[$cat]);
    if ( !in_array($cat,$this->cats) ) return array();
    if ( empty($this->appIds) ) $this->index();
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 ) $max = count($this->appCats[$cat]);
    else $max = $start + $limit;
    $apps = [];
    for ($i=$start;$i<=$max,$i<count($this->appCats[$cat]);++$i) {
      if ( $i>=$max ) break;
      else $apps[] = $this->data->application[$this->appCats[$cat][$i]];
    }
    return $apps;
  }

  /** Get all apps by a given author
   * @method getAppsByAuthor
   * @param str author name of the author
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsByAuthor($author,$start=0,$limit=null) {
    if ( empty($this->autNames) ) $this->index();
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 ) $max = count($this->autNames[$author]);
    else $max = $start + $limit;
    $this->fullHits = 0;
    $apps = []; $i=0;
    foreach( $this->autNames[$author] as $aut ) {
      if ( $i>=$max ) ++$this->fullHits;
      elseif ( $i < $start ) { ++$this->fullHits; continue; }
      else { $apps[] = $this->data->application[$aut]; ++$this->fullHits; }
    }
    return $apps;
  }

  /** Get all apps using a given license
   * @method getAppsByLicense
   * @param string license
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsByLicense($license,$start=0,$limit=null) {
    if (empty ($this->appLicenses) ) $this->index();
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 ) $max = count($this->appLicenses[$license]);
    else $max = $start + $limit;
    $this->fullHits = count($this->appLicenses[$license]);
    $apps = [];
    for ($i=$start;$i<=$max,$i<count($this->appLicenses[$license]);++$i) {
      if ( $i>=$max ) break;
      else { $apps[] = $this->data->application[$this->appLicenses[$license][$i]]; }
    }
    return $apps;
  }

  /** Helper to filter app by dates with pager-limit
   * @method protected filterDateRange
   * @param str date 'YYYY-MM-DD'
   * @param int limit how many apps to return. Default is to return all (limit=NULL).
   * @param int start position to start with when paging. Default is 0 (the first app).
   * @param ref array list appList to filter
   * @return array apps array[0..n] of object app
   */
  protected function filterDateRange($date,$limit,$start,&$list) {
    if ( empty($this->appIds) ) $this->index();
    if ( $limit==0 ) $max = $this->appcount;
    else $max = $start + $limit;
    $this->fullHits = 0;
    $i = 0;
    $apps = [];
    foreach($list as $d=>$a) {
      if ( strtotime($d) >= strtotime($date) ) foreach($a as $b) {
        ++$i;
        if ( $i<=$start ) { ++$this->fullHits; continue; }
        elseif ( $i>$max ) ++$this->fullHits;
        else { $apps[] = $this->data->application[$b]; ++$this->fullHits; }
      }
      else continue;
    }
    return $apps;
  }

  /** Get all apps build on a given date or later
   * @method getAppsBuildSince
   * @param str date 'YYYY-MM-DD'
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsBuildSince($date,$start=0,$limit=null) {
    if ( $limit===null ) $limit = $this->limit;
    return $this->filterDateRange($date,$limit,$start,$this->appBuilds);
  }

  /** Get all apps added to the repo on a given date or later
   * @method getAppsAddedSince
   * @param str date 'YYYY-MM-DD'
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsAddedSince($date,$start=0,$limit=null) {
    if ( $limit===null ) $limit = $this->limit;
    return $this->filterDateRange($date,$limit,$start,$this->appAddeds);
  }

  /** Get all apps whos repo entry was updated on a given date or later
   * @method getAppsUpdatedSince
   * @param str date 'YYYY-MM-DD'
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function getAppsUpdatedSince($date,$start=0,$limit=null) {
    if ( $limit===null ) $limit = $this->limit;
    return $this->filterDateRange($date,$limit,$start,$this->appUpdates);
  }

  /** Search for apps matching a keyword/string anywhere (name, desc, etc.)
   *  Passed string is taken literally, and matched case-insensitive. No fuzzy-logic
   *  or any other sophisticated stuff for now.
   * @method searchApps
   * @param string keyword Keyword/String to search for
   * @param optional int start position to start with when paging. Default is 0 (the first app).
   * @param optional int limit how many apps to return. Default is self::limit (set by constructor or self::setLimit).
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function searchApps($keyword,$start=0,$limit=null) {
    $this->fullHits = 0;
    if ( !$this->ftsEnabled ) return array(); // not indexed, no search
    if ( empty($this->ftsIndex) ) $this->index();
    if ( $limit===null ) $limit = $this->limit;
    if ( $limit==0 ) $max = $this->appcount;
    else $max = $start + $limit;
    $keyword = strtolower($keyword);
    $apps = [];
    $i=0;
    foreach ($this->ftsIndex as $key=>$val) {
      if ( $i==$this->appcount ) break;
      elseif ( $i>=$max && strpos($val,$keyword)!==false ) { ++$i; ++$this->fullHits; }
      elseif (strpos($val,$keyword)!==false) {
        if ( $i<$start ) {
          ++$i;
          ++$this->fullHits;
          continue;
        } else {
          $apps[] = $this->data->application[$key];
          ++$i;
          ++$this->fullHits;
        }
      }
    }
    return $apps;
  }

  /** Intersecting two app lists (returning only apps present in both).
   *  This can be used to select apps meeting multiple criteria, e.g.
   *  fdroid->intersect(fdroid->searchApps('foobar'),fdroid->getAppsByCat('System'))
   *  would only return apps from the System category mentioning 'foobar'.
   * @method intersect
   * @param array apps1
   * @param array apps2
   * @return array apps array[0..n] of object app
   * @see getAppList
   */
  public function intersect($arr1,$arr2,$start=0,$limit=null) {
    if ( $limit===null ) $limit = $this->limit;
    if ( empty($arr1) || empty($arr2) ) return [];
    $apps = [];
    foreach($arr1 as $a) {
      foreach($arr2 as $b) {
        if ( $a->id == $b->id ) $apps[] = $a;
      }
    }
    $this->fullHits = count($apps);
    if ($start==0 && $limit==0) return $apps;
    else return array_slice($apps,$start,$start+$limit);
  }

  /** Get data for an app by its package_name
   * @method getAppById
   * @param string id package_name
   * @return object app
   * @see getAppList
   */
  public function getAppById($id) {
    if ( empty($this->appIds) ) $this->index();
    if ( !isset($this->appIds[$id]) || !isset($this->data->application[$this->appIds[$id]]) ) return new stdClass();
    return $this->data->application[$this->appIds[$id]];
  }

  /** Get data for an app by its app_name
   * @method getAppByName
   * @param string id package_name
   * @return object app
   * @see getAppList
   */
  public function getAppByName($id) {
    if ( empty($this->appIds) ) $this->index();
    if ( !isset($this->data->application[$this->appNames[$id]]) ) return new stdClass();
    return $this->data->application[$this->appNames[$id]];
  }
}
?>