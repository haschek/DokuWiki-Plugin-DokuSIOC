<?php
/**
 * DokuSIOC - 
 *
 * version 0.1
 *
 * 
 *
 * METADATA
 *
 * @author    Michael Haschke @ eye48.com
 * @copyright 2009 Michael Haschke
 * @license   http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License 2.0 (GPLv2)
 * @version   0.1
 *
 * WEBSITES
 *
 * @link      http://eye48.com/go/dokusioc Plugin Website and Overview
 *
 * LICENCE
 * 
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * @link      http://www.gnu.org/licenses/old-licenses/gpl-2.0.html GNU General Public License 2.0 (GPLv2)
 *
 * CHANGELOG
 *
 * 0.1
 * - exchange licence b/c CC-BY-SA was incompatible with GPL
 * - restructuring code base
 * - fix: wrong meta link for revisions
 * - add: possibility to send noindex by x-robots-tag via HTTP header
 * - add: soft check for requested application type
 * - mod: use search method to get container content on next sub level
 * poc
 * - proof of concept release under CC-BY-SA
 **/
 
if(!defined('DOKU_INC')) die();
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');
 
class action_plugin_dokusioc extends DokuWiki_Action_Plugin {


    /* -- Methods to manage plugin ------------------------------------------ */

    /**
    * return some info
    */
    function getInfo(){
        return array(
	         'author' => 'Michael Haschke',
	         'email'  => 'haschek@eye48.com',
	         'date'   => '2009-07-08',
	         'name'   => 'DokuSIOC',
	         'desc'   => 'Adds alternate link to SIOC-RDF document to meta header, creates SIOC version of wiki content and checks the requested application type.',
	         'url'    => 'http://eye48.com/go/dokusioc'
	         );
    }
 
    /**
    * Register its handlers with the DokuWiki's event controller
    */
    function register(&$controller)
    {
        // test the requested action
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE',  $this, 'checkAction', $controller);
        // pingthesemanticweb.com
        if ($this->getConf('pingsw')) $controller->register_hook('ACTION_SHOW_REDIRECT', 'BEFORE',  $this, 'pingService', $controller);
    }
 
    /* -- Event handlers ---------------------------------------------------- */

    function checkAction($action, $controller)
    {
        global $INFO;
        
        if ($action->data == 'export_siocxml')
        {
            // give back rdf
            $this->exportSioc();
        }
        elseif ($action->data == 'show' && $INFO['perm'] && !defined('DOKU_MEDIADETAIL') && ($INFO['exists'] || getDwUserInfo($INFO['id'],$this)) && !isHiddenPage($INFO['id']))
        {
            // add meta link to html head
            $controller->register_hook('TPL_METAHEADER_OUTPUT', 'BEFORE',  $this, 'createRdfLink');
        }
    }
    
    function pingService($data, $controller)
    {
        // TODO: test acl
        // TODO: write in message queue (?)
        
        if ($data->data['preact'] == array('save'=>'Save') || $data->data['preact'] == 'save')
        {
            //die('http://pingthesemanticweb.com/rest/?url='.urlencode(getAbsUrl(wl($data->data['id']))));
            $ping = fopen('http://pingthesemanticweb.com/rest/?url='.urlencode(getAbsUrl(wl($data->data['id']))),'r');
            fclose($ping);
        }
    }
    
    /**
    */
    function createRdfLink(&$event, $param)
    {
        global $ID, $INFO, $conf;
        
        // Test for hidden pages
        
        if (isHiddenPage($ID))
            return false;
        
        // Get type of SIOC content
        
        $sioc_type = $this->_getContenttype();
        
        // Test for valid types
        
        if (!(($sioc_type == 'post' && $INFO['exists']) || $sioc_type == 'user' || $sioc_type == 'container'))
            return false;
        
        // Test for permission
        
        if (!$INFO['perm']) // not enough rights to see the wiki page
            return false;

        $userinfo = getDwUserInfo($ID, $this);
        
        // Create attributes for meta link
        
        $metalink['type'] = 'application/rdf+xml';
        $metalink['rel'] = 'meta';
        
        switch ($sioc_type)
        {
            case 'container':
                $title = htmlentities("SIOC document as RDF-XML for wiki '".$conf['title']."'");
                $queryAttr = array('type'=>'container');
                break;
                
            case 'user':
                $title = htmlentities("SIOC document as RDF-XML for user '".$userinfo['name']."'");
                $queryAttr =  array('type'=>'user');
                break;

            case 'post':
            default:
                $title = htmlentities("SIOC document as RDF-XML for article '".$INFO['meta']['title']."'");
                $queryAttr =  array('type'=>'post');
                if (isset($_GET['rev']) && $_GET['rev'] == intval($_GET['rev']))
                    $queryAttr['rev'] = $_GET['rev'];
                break;
        }
    
        $metalink['title'] = $title;
        $metalink['href'] = getAbsUrl(exportlink($ID, 'siocxml', $queryAttr, false, '&'));

        // forward to rdfxml document if requested
        if ($this->isRdfXmlRequest())
        {
            header('Location: '.$metalink['href']);
        }
        else
        {
            $event->data["link"][] = $metalink;
        }
        
        return;
    }
    
    /* -- public class methods ---------------------------------------------- */

    function exportSioc()
    {
        global $ID, $INFO, $conf, $REV, $auth;
        
        // Test for hidden pages
        
        if (isHiddenPage($ID))
            $this->_exit("HTTP/1.0 404 Not Found");
        
        // Get type of SIOC content
        
        $sioc_type = $this->_getContenttype();
        
        // Test for valid types
        
        if (!(($sioc_type == 'post' && $INFO['exists']) || $sioc_type == 'user' || $sioc_type == 'container'))
            $this->_exit("HTTP/1.0 404 Not Found");
        
        // Test for permission
        
        if (!$INFO['perm']) // not enough rights to see the wiki page
            $this->_exit("HTTP/1.0 401 Unauthorized");


        // Include SIOC libs
        
        require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'sioc_inc.php');
        require_once(dirname(__FILE__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'sioc_dokuwiki.php');
        
        // Create exporter
        
        $rdf = new SIOCExporter();
        $rdf->setURLParameters('type', 'id', 'page', false);
        
        // Create SIOC-RDF content
        
        switch ($sioc_type)
        {
            case 'container':
                $rdf = $this->_exportContainercontent($rdf);
                break;
                
            case 'user':
                $rdf = $this->_exportUsercontent($rdf);
                break;

            case 'post':
            default:
                $rdf = $this->_exportPostcontent($rdf);
                break;
        }
    
        // export
        if ($this->getConf('noindx')) 
            header("X-Robots-Tag: noindex", true);
        $rdf->export();
        
        die();
    }
    
    function isRdfXmlRequest()
    {
        // get accepted types
        $http_accept = trim($_SERVER['HTTP_ACCEPT']);
        
        // soft check, route to RDF when client requests it (don't check quality of request)
        if ($this->getConf('softck') && strpos($http_accept, 'application/rdf+xml') !== false)
        {
            return true;
        }
        
        // hard check, only serve RDF if it is requested first or equal to first type
    
        // save accepted types in array
        $accepted = explode(',', $http_accept);
        
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

    /* -- private helpers --------------------------------------------------- */
    
    function _getContenttype()
    {
        global $ID, $conf;

        // check for type if unknown
        if (!$_GET['type'])
        {
            $userinfo = getDwUserInfo($ID, $this);
            
            if ($userinfo)
            {
                $type = 'user';
            }
            elseif ($ID == $conf['start'])
            {
                $type = 'container';
            }
            else
            {
                $type = 'post';
            }
            
        }
        else
        {
            $type = $_GET['type'];
        }
        
        return $type;
    
    }
    
    function _exportPostcontent($exporter)
    {
        global $ID, $INFO, $REV, $conf;

        $exporter->setParameters($INFO['meta']['title'],
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
                                        $exporter->siocURL('user', $dwuserpage_id)
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
        $exporter->addObject($wikipage);
        if ($INFO['editor'] && $this->getConf('userns')) $exporter->addObject($pageuser);
        
        return $exporter;
        
    }
    
    function _exportContainercontent($exporter)
    {
        global $ID, $INFO, $conf;
    
        $exporter->setParameters($conf['title'],
                            getAbsUrl(),
                            getAbsUrl().'doku.php?do=export_siocxml&',
                            'utf-8',
                            'http://eye48.com/go/dokusioc'
                            );

        // create container object
        $wikicontainer = new SIOCDokuWikiContainer($ID, getAbsUrl(wl($ID)));

        /* container is type=wiki */ if ($ID == $conf['start']) $wikicontainer->isWiki();
        /* sioc:name */ if ($INFO['exists']) $wikicontainer->addTitle($INFO['meta']['title']);

        // search next level entries (posts, sub containers) in container
        require_once(DOKU_INC.'inc/search.php');
        $dir  = utf8_encodeFN(str_replace(':','/',$ID));
        $entries = array();
        $posts = array();
        $containers = array();
        search($entries,$conf['datadir'],'search_index',array('ns' => $ID),$dir);
        foreach ($entries as $entry)
        {
            if ($entry['type'] === 'f')
            {
                // wikisite
                $posts[] = $entry;
            }
            elseif($entry['type'] === 'd')
            {
                // sub container
                $containers[] = $entry;
            }
        }
        
        if (count($posts)>0) $wikicontainer->addArticles($posts);
        if (count($containers)>0) $wikicontainer->addContainers($containers);

        //print_r($containers);die();
        
        // add container to exporter
        $exporter->addObject($wikicontainer);
        
        return $exporter;
    }
    
    function _exportUsercontent($exporter)
    {
        global $ID;
        
        // get user info
        $userinfo = $auth->getUserData($ID,$this);
        $exporter->setParameters($userinfo['name'],
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
        $exporter->addObject($wikiuser);
        
        return $exporter;
    }
    
    function _exit($headermsg)
    {
        header($headermsg);
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



