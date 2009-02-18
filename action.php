<?php
/**
 */
 
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
 
class action_plugin_dokusioc extends DokuWiki_Action_Plugin {
 
    /**
    * return some info
    */
    function getInfo(){
        return array(
	         'author' => 'Michael Haschke',
	         'email'  => 'haschek@eye48.com',
	         'date'   => '2009-02-16',
	         'name'   => 'SIOC Export (action plugin component)',
	         'desc'   => 'Used to add alternate link of SIOC RDF document to meta header.',
	         'url'    => 'http://eye48.com/go/dokusioc'
	         );
    }
 
    /**
    * Register its handlers with the DokuWiki's event controller
    */
    function register(&$controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE',  $this, '_check_action', $controller);
        if ($this->getConf('pingsw')) $controller->register_hook('ACTION_SHOW_REDIRECT', 'BEFORE',  $this, '_ping', $controller);
    }
 
    function _check_action($action, $controller)
    {
        global $INFO;
        
        if ($action->data == 'export_siocxml')
        {
            // give back rdf
            $this->_export_sioc();
        }
        elseif ($action->data == 'show' && $INFO['perm'] && !defined('DOKU_MEDIADETAIL') && ($INFO['exists'] || getDwUserInfo($INFO['id'],$this)) && !isHiddenPage($INFO['id']))
        {
            $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE',  $this, '_add_xmllink');
        }
    }
    
    function _ping($data, $controller)
    {
        // TODO: test acl
        // TODO: write in message queue (?)
        //print_r($data->data['preact']); die();
        if ($data->data['preact'] == array('save'=>'Save') || $data->data['preact'] == 'save')
        {
            //die('http://pingthesemanticweb.com/rest/?url='.urlencode(getAbsUrl(wl($data->data['id']))));
            $ping = fopen('http://pingthesemanticweb.com/rest/?url='.urlencode(getAbsUrl(wl($data->data['id']))),'r');
            fclose($ping);
        }
    }
    
    /**
    */
    function _add_xmllink(&$event, $param)
    {
        global $ID, $INFO, $auth, $conf;
        
        $userinfo = getDwUserInfo($ID, $this);
        
        if ($userinfo)
        {
            // user page -> type=user
            $metalink = array (
                "type" => "application/rdf+xml",
                "rel" => "meta",
                "title" => htmlentities("SIOC document as RDF-XML for user '".$userinfo['name']."'"),
                "href" => getAbsUrl(exportlink($ID, 'siocxml', array('type'=>'user'), false, '&'))
            );
        }
        elseif ($ID == $conf['start'])
        {
            // wiki container -> type=container
            $metalink = array (
                "type" => "application/rdf+xml",
                "rel" => "meta",
                "title" => htmlentities("SIOC document as RDF-XML for wiki '".$conf['title']."'"),
                "href" => getAbsUrl(exportlink($ID, 'siocxml', array('type'=>'container'), false, '&'))
            );
        }
        else
        {
            // wiki article -> type=post
            $metalink = array (
                "type" => "application/rdf+xml",
                "rel" => "meta",
                "title" => htmlentities("SIOC document as RDF-XML for article '".$INFO['meta']['title']."'"),
                "href" => getAbsUrl(exportlink($ID, 'siocxml', '', false, '&'))
            );
        }
        
        // stright forward to rdfxml document if requested
        if (isRdfXmlRequest())
        {
            header('Location: '.$metalink['href']);
        }
        else
        {
            $event->data["link"][] = $metalink;
        }
    }
    
    function _export_sioc()
    {
        global $ID, $INFO, $conf, $REV, $auth;
        //$ID = $id; //necessary for correct metadata handling 
        // $text = p_cached_output(wikiFN($ID),'xhtml');
        
        // check for type if unknown
        if (!$_GET['type'])
        {
            $userinfo = getDwUserInfo($ID, $this);
            
            if ($userinfo)
            {
                $sioc_type = 'user';
            }
            elseif ($ID == $conf['start'])
            {
                $sioc_type = 'container';
            }
            else
            {
                $sioc_type = 'post';
            }
            
        }
        else
        {
            $sioc_type = $_GET['type'];
        }
        
        if (!isHiddenPage($ID) &&
            (($sioc_type == 'post' && $INFO['exists']) || $sioc_type == 'user' || $sioc_type == 'container'))
        {
            if (!$INFO['perm'])
            {
                // not enough rights to see the wiki page
                header("HTTP/1.0 401 Unauthorized");
            }
            else
            {
                //print_r($INFO); die();

                // include sioc libs
                require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'sioc_inc.php');
                require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'sioc_dokuwiki.php');
                
                // create exporter
                $rdf = new SIOCExporter();
                $rdf->setURLParameters('type', 'id', 'page', false);
                
                // 
                
                switch ($sioc_type)
                {
                    case 'container':
                        $rdf->setParameters($conf['title'],
                                            getAbsUrl(),
                                            getAbsUrl().'doku.php?do=export_siocxml&',
                                            'utf-8',
                                            'http://eye48.com/go/dokusioc'
                                           );
                        // create container object
                        $wikicontainer = new SIOCDokuWikiContainer($ID, getAbsUrl(wl($ID)));
                        /* container is type=wiki */ if ($ID == $conf['start']) $wikicontainer->isWiki();
                        /* sioc:name */ if ($INFO['exists']) $wikicontainer->addTitle($INFO['meta']['title']);
                        // add links to wiki articles
                        $offset = 0; $conf['recent'] = 10; // set to 10 manually
                        if (!$_GET['page']) $_GET['page'] = 1;
                        if ($_GET['page']) $offset = ($_GET['page']-1) * $conf['recent'];
                        $recents = getRecents($offset, $conf['recent'], $ID, RECENTS_SKIP_DELETED + RECENTS_SKIP_MINORS);
                        if (count($recents)>0) $wikicontainer->addArticles($recents, $conf['recent'], $_GET['page']);
                        //print_r($recents);die();
                        
                        // add container to exporter
                        $rdf->addObject($wikicontainer);
                        break;
                    case 'user':    // type='user'
                        // get user info
                        $userinfo = $auth->getUserData($ID,$this);
                        $rdf->setParameters($userinfo['name'],
                                            getAbsUrl(),
                                            getAbsUrl().'doku.php?do=export_siocxml&',
                                            'utf-8',
                                            'http://eye48.com/go/dokusioc'
                                           );
                        // create user object
                        //print_r($userinfo); die();
                        $wikiuser = new SIOCDokuWikiUser($ID, getAbsUrl(wl($ID)), $userid, $userinfo['name'], $userinfo['mail']);
                        /* TODO: avatar (using Gravatar) */
                        /* TODO: creator_of */
                        // add user to exporter
                        $rdf->addObject($wikiuser);
                        break;
                    default:        // type='post'
                        //print_r($INFO); die();
                    
                        $rdf->setParameters($INFO['meta']['title'],
                                            $this->_getDokuUrl(),
                                            $this->_getDokuUrl().'doku.php?do=export_siocxml&',
                                            'utf-8',
                                            'http://eye48.com/go/dokusioc'
                                           );

                        // create user object
                        // $id, $uri, $name, $email, $homepage='', $foaf_uri='', $role=false, $nick='', $sioc_url='', $foaf_url=''
                        $dwuserpage_id = cleanID($this->getConf('userns')).($conf['useslash']?'/':':').$INFO['editor'];
                        if ($INFO['editor'] && $this->getConf('userns'))
                            $pageuser = new SIOCUser($INFO['editor'],
                                                     $this->_getDokuUrl(wl($dwuserpage_id)), // user page
                                                     $INFO['meta']['contributor'][$INFO['editor']],
                                                     getDwUserInfo($dwuserpage_id,$this,'mail'),
                                                     '', // no homepage is saved for dokuwiki user
                                                     '#'.$INFO['editor'], // local uri
                                                     false, // no roles right now
                                                     '', // no nick name is saved for dokuwiki user
                                                     $rdf->siocURL('user', $dwuserpage_id)
                                                    );
                        
                        // create wiki page object
                        $wikipage = new SIOCDokuWikiArticle($ID, // id
                                                            $this->_getDokuUrl(wl($ID,($REV?"rev=$REV":''))), // url
                                                            $INFO['meta']['title'], // subject
                                                            rawWiki($ID,$REV) // body (content)
                                                           );
                        /* encoded content   */ $wikipage->addContentEncoded(p_cached_output(wikiFN($ID,$REV),'xhtml'));
                        /* make time         */ $wikipage->addCreated($this->_getDate($INFO['meta']['date']['created'],$INFO['meta']['date']['modified']));
                        /* creator           */ if ($INFO['editor'] && $this->getConf('userns')) $wikipage->addCreator(array('foaf:maker'=>'#'.$INFO['editor'],'sioc:creator'=>$this->_getDokuUrl(wl($dwuserpage_id))));
                        /* intern wiki links */ $wikipage->addLinks($INFO['meta']['relation']['references']);
                        // TODO: addLinksExtern

                        /* previous and next revision */
                        $pagerevs = getRevisions($ID,0,$conf['recent']+1);
                        $prevrev = false; $nextrev = false;
                        if (!$REV)
                        {
                            // latest revision, previous rev is on top in array
                            $prevrev = 0;
                        }
                        else
                        {
                            // other revision
                            $currentrev = array_search($REV, $pagerevs);
                            if ($currentrev !== false)
                            {
                                $prevrev = $currentrev + 1;
                                $nextrev = $currentrev - 1;
                            }
                        }
                        if ($prevrev !== false && $prevrev > -1 && page_exists($ID,$pagerevs[$prevrev]))
                        /* previous revision*/ $wikipage->addVersionPrevious($pagerevs[$prevrev]);
                        if ($nextrev !== false && $nextrev > -1 && page_exists($ID,$pagerevs[$nextrev]))
                        /* next revision*/ $wikipage->addVersionNext($pagerevs[$nextrev]);

                        /* latest revision   */ if ($REV) $wikipage->addVersionLatest();
                        // TODO: topics
                        // TODO: container
                        /* has_container     */ $wikipage->addContainer($conf['start']); 
                        /* has_space         */ if ($this->getConf('owners')) $wikipage->addSite($this->getConf('owners')); 
                        // TODO: dc:contributor / has_modifier
                        // TODO: attachment (e.g. pictures in that dwns)
                        
                        // add wiki page to exporter
                        $rdf->addObject($wikipage);
                        if ($INFO['editor'] && $this->getConf('userns')) $rdf->addObject($pageuser);
                        
                        break;
                }
            
                // export
                $rdf->export();
                
            }
        }
        else
        {
            // wiki page does not exists, send 404
            header("HTTP/1.0 404 Not Found");
        }
        die();
    }
    
    function _getDokuUrl($url=null)
    {
        return getAbsUrl($url);
    }
    
    function _getDate($date, $date_alt=null)
    {
        if (!$date) $date = $date_alt;
        return date('c',$date);
    }
    
}

if (!function_exists('getAbsUrl'))
{
    function getAbsUrl($url=null)
    {
        if ($url == null) $url = DOKU_BASE;
        return str_replace(DOKU_BASE, DOKU_URL, $url);
    }
}

if (!function_exists('getDwUserEmail'))
{
    function getDwUserEmail($user)
    {
        global $auth;
        if ($info = $auth->getUserData($user))
        {
            return $info['mail'];
        }
        else
        {
            return false;
        }
    }
}

if (!function_exists('getDwUserInfo'))
{
    function getDwUserInfo($id, $pobj, $key = null)
    {
        global $auth, $conf;
        
        if (!$pobj->getConf('userns')) return false;
        
        // get user id
        $userid = str_replace(cleanID($pobj->getConf('userns')).($conf['useslash']?'/':':'),'',$id);
        
        if ($info = $auth->getUserData($userid))
        {
            if ($key)
            {
                return $info['key'];
            }
            else
            {
                return $info;
            }
        }
        else
        {
            return false;
        }
    }
}

if (!function_exists('isRdfXmlRequest'))
{
    function isRdfXmlRequest()
    {
        // save accepted types in array
        $accepted = explode(',',trim($_SERVER['HTTP_ACCEPT']));
        
        if (count($accepted)>0)
        {
            // extract accepting ratio
            $test_accept = array();
            foreach($accepted as $format)
            {
                $formatspec = explode(';',$format);
                $k = trim($formatspec[0]);
                if (count($formatspec)==2)
                {
                    $test_accept[$k] = trim($formatspec[1]);
                }
                else
                {
                    $test_accept[$k] = 'q=1.0';
                }
            }
            
            // sort by ratio
            arsort($test_accept); $accepted_order = array_keys($test_accept);
            
            if ($accepted_order[0] == 'application/rdf+xml' || $test_accept['application/rdf+xml'] == 'q=1.0')
            {
                return true;
            }
        }

        return false;

    }
}



