<?php

/**
 * SIOC::WikiDokuArticle object
 *
 * Contains information about a wiki article
 */
class SIOCDokuWikiArticle extends SIOCObject
{

    var $_type = 'sioct:WikiArticle';

    var $_id = null;
    var $_url = null;
    //var $_api = null;
    var $_subject = null;
    //var $_redirpage = null;
    var $_creator = array();
    var $_created = null;
    //var $_topics = array();
    var $_links = array();
    //var $_ext_links = array();
    var $_previous_version = null;
    var $_next_version = null;
    var $_latest_version = false; // show latest version
    //var $_has_discussion = null;
    var $_has_container = null;
    var $_has_space = null;
    var $_content = null;
    var $_content_encoded = null;

    function SIOCDokuWikiArticle($id, $url, $subject, $content)
    {
        $this->_id = $id;
        $this->_url = $url;
        $this->_subject = $subject;
        $this->_content = $content;
        return;
    }
    
    function addCreated($created) { $this->_created = $created; }
    function addCreator($creator) { $this->_creator = $creator; }
    function addLinks($links) { if (is_array($links) && count($links)>0) $this->_links = $links; }
    //function addLinksExtern($links) { if (is_array($links) && count($links)>0) $this->_ext_links = $links; }
    function addVersionPrevious($rev) { $this->_previous_version = $rev; }
    function addVersionNext($rev) { $this->_next_version = $rev; }
    function addVersionLatest() { $this->_latest_version = true; }
    function addContentEncoded($encoded) { $this->_content_encoded = $encoded; }
    function addContainer($id) { $this->_has_container = $id; }
    function addSite($url) { $this->_has_space = $url; }
    

    function getContent( &$exp )
    {
        $rdf = '<'.$this->_type." rdf:about=\"" . clean($this->_url) . "\">\n";
        if ($this->_subject)
        {
            $rdf .= "\t<dc:title>" . clean($this->_subject) . "</dc:title>\n";
            // if(strcmp($this->_has_container, 'http://en.wikipedia.org')===0)
            //    $rdf .= "\t<foaf:primaryTopic rdf:resource=\"".clean('http://dbpedia.org/resource/'.$this->_subject)."\"/>\n";
        }

        if (is_array($this->_creator))
        {
            if ($this->_creator['foaf:maker'])
                $rdf .= "\t<foaf:maker rdf:resource=\"".$this->_creator['foaf:maker']."\"/>\n";
            if ($this->_creator['sioc:creator'])
                $rdf .= "\t<sioc:has_creator rdf:resource=\"".$this->_creator['sioc:creator']."\"/>\n";
        }

        if ($this->_created)
        {
            $rdf .= "\t<dcterms:created>" . $this->_created . "</dcterms:created>\n";
        }
        
        if ($this->_has_space)
        {
            $rdf .= "\t<sioc:has_space><sioc:Site rdf:about=\"".clean($this->_has_space)."\"/></sioc:has_space>\n";
        }
        
        if($this->_has_container) {
                $rdf .= "\t<sioc:has_container>\n";
                $rdf .= "\t\t<sioct:Wiki rdf:about=\"".clean(getAbsUrl(wl($this->_has_container)))."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"".$exp->siocURL('container', $this->_has_container)."\"/>\n";
                $rdf .= "\t\t</sioct:Wiki>\n";
                $rdf .= "\t</sioc:has_container>\n";
        }
        
        if ($this->_content)
        {
            $rdf .= "\t<sioc:content><![CDATA[" . pureContent($this->_content) . "]]></sioc:content>\n";
        }

        if ($this->_content_encoded)
        {
            $rdf .= "\t<content:encoded><![CDATA[" . $this->_content_encoded . "]]></content:encoded>\n";
        }

        /*
        if(is_array($this->_topics)) {
            foreach($this->_topics as $topic=>$url) {
                $rdf .= "\t<sioc:topic>\n";
                $rdf .= "\t\t<sioct:Category rdf:about=\"" . clean($url) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"" .
                        clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki='.$url);
                if ($this->_api) $rdf .= clean("&api=" . $this->_api);
                $rdf .= "\"/>\n";
                $rdf .= "\t\t</sioct:Category>\n";
                $rdf .= "\t</sioc:topic>\n";
            }
        }
        */
        
        if (is_array($this->_links) && count($this->_links)>0)
        {
            foreach($this->_links as $link_id => $link_exists)
            {
                if ($link_exists && !isHiddenPage($link_id))
                {
                    $rdf .= "\t<sioc:links_to>\n";
                    $rdf .= "\t\t<sioct:WikiArticle rdf:about=\"". clean(getAbsUrl(wl($link_id))) ."\">\n";
                    $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $exp->siocURL('post', $link_id) ."\"/>\n";
                    $rdf .= "\t\t</sioct:WikiArticle>\n";
                    $rdf .= "\t</sioc:links_to>\n";
                }
            }
        }
        
        /*
        if(is_array($this->_ext_links)) {
            foreach($this->_ext_links as $label=>$url) {
                $rdf .= "\t<sioc:links_to rdf:resource=\"" . clean($url) ."\"/>\n";
            }
        }
        */
        
        if($this->_previous_version) {
                $rdf .= "\t<sioc:previous_version>\n";
                $rdf .= "\t\t<sioct:WikiArticle rdf:about=\"". clean(getAbsUrl(wl($this->_id, 'rev='.$this->_previous_version))) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $exp->siocURL('post', $this->_id.$exp->_urlseparator.'rev'.$exp->_urlequal.$this->_previous_version) ."\"/>\n";
                $rdf .= "\t\t</sioct:WikiArticle>\n";
                $rdf .= "\t</sioc:previous_version>\n";

                /* If there is support for inference and transitivity the following is not needed */
                $rdf .= "\t<sioc:earlier_version>\n";
                $rdf .= "\t\t<sioct:WikiArticle rdf:about=\"". clean(getAbsUrl(wl($this->_id, 'rev='.$this->_previous_version))) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $exp->siocURL('post', $this->_id.$exp->_urlseparator.'rev'.$exp->_urlequal.$this->_previous_version) ."\"/>\n";
                $rdf .= "\t\t</sioct:WikiArticle>\n";
                $rdf .= "\t</sioc:earlier_version>\n";

        }

        if($this->_next_version) {
                $rdf .= "\t<sioc:next_version>\n";
                $rdf .= "\t\t<sioct:WikiArticle rdf:about=\"". clean(getAbsUrl(wl($this->_id, 'rev='.$this->_next_version))) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $exp->siocURL('post', $this->_id.$exp->_urlseparator.'rev'.$exp->_urlequal.$this->_next_version) ."\"/>\n";
                $rdf .= "\t\t</sioct:WikiArticle>\n";
                $rdf .= "\t</sioc:next_version>\n";
                
                /* If there is support for inference and transitivity the following is not needed */
                $rdf .= "\t<sioc:later_version>\n";
                $rdf .= "\t\t<sioct:WikiArticle rdf:about=\"". clean(getAbsUrl(wl($this->_id, 'rev='.$this->_next_version))) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $exp->siocURL('post', $this->_id.$exp->_urlseparator.'rev'.$exp->_urlequal.$this->_next_version) ."\"/>\n";
                $rdf .= "\t\t</sioct:WikiArticle>\n";
                $rdf .= "\t</sioc:later_version>\n";
        }

        if($this->_latest_version) {
                $rdf .= "\t<sioc:latest_version>\n";
                $rdf .= "\t\t<sioct:WikiArticle rdf:about=\"". clean(getAbsUrl(wl($this->_id))) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $exp->siocURL('post', $this->_id) ."\"/>\n";
                $rdf .= "\t\t</sioct:WikiArticle>\n";
                $rdf .= "\t</sioc:latest_version>\n";
        }

        /*
        if($this->_has_discussion && (strpos($this->_has_discussion, 'Talk:Talk:') == FALSE)) {
                $rdf .= "\t<sioc:has_discussion>\n";
                $rdf .= "\t\t<sioct:WikiArticle rdf:about=\"" . clean($this->_has_discussion) ."\">\n";
                $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"" .
                        clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki='.$this->_has_discussion);
                if ($this->_api) $rdf .= clean("&api=" . $this->_api);
                $rdf .= "\"/>\n";
                $rdf .= "\t\t</sioct:WikiArticle>\n";
                $rdf .= "\t</sioc:has_discussion>\n";
        }
        */
        
        /*
        if($this->_redirpage)
        {
            $rdf .= "\t<owl:sameAs rdf:resource=\"" . clean($this->_redirpage) ."\"/>\n";
            $rdf .= "\t<rdfs:seeAlso rdf:resource=\"" . 
                        clean('http://ws.sioc-project.org/mediawiki/mediawiki.php?wiki='.$this->_redirpage);
            if ($this->_api) $rdf .= clean("&api=" . $this->_api);
            $rdf .= "\"/>\n";
        }
        */

        $rdf .=  "</".$this->_type.">\n";
        return $rdf;
    }
}

/**
 * SIOC::DokuWikiUser object
 *
 * Contains information about a wiki user
 */
class SIOCDokuWikiUser extends SIOCObject
{

    var $type = 'user';

    var $_id;
    var $_nick;
    var $_url;
    var $_name;
    var $_email;
    var $_sha1;
    var $_homepage;
    var $_foaf_uri;
    var $_role;
    var $_sioc_url;
    var $_foaf_url;

    function SIOCDokuWikiUser($id, $url, $userid, $name, $email)
    {
        $this->_id = $id;
        $this->_nick = $userid;
        $this->_name = $name;
        //$this->_email = $email;
        $this->_url = $url;
 
        if (preg_match_all('/^.+@.+\..+$/Ui', $email, $check, PREG_SET_ORDER)) {
            if (preg_match_all('/^mailto:(.+@.+\..+$)/Ui', $email, $matches, PREG_SET_ORDER)) {
                $this->_email = $email; 
                $this->_sha1 = sha1($email);
            } else {
                $this->_email = "mailto:".$email; 
                $this->_sha1 = sha1("mailto:".$email);
            }
        }
     }

    function getContent( &$exp ) {
        $rdf = "<sioc:User rdf:about=\"" . clean($this->_url) ."\">\n";
        if($this->_nick) $rdf .= "\t<sioc:name>" . $this->_nick . "</sioc:name>\n";
        if($this->_email) {
            if ($exp->_export_email) { $rdf .= "\t<sioc:email rdf:resource=\"" . $this->_email ."\"/>\n"; }
            $rdf .= "\t<sioc:email_sha1>" . $this->_sha1 . "</sioc:email_sha1>\n";
        }
        if($this->_role) {
            $rdf .= "\t<sioc:has_function>\n";
            $rdf .= "\t\t<sioc:Role>\n";
            $rdf .= "\t\t\t<sioc:name>" . $this->_role . "</sioc:name>\n";
            $rdf .= "\t\t</sioc:Role>\n";
            $rdf .= "\t</sioc:has_function>\n";
        }
        $rdf .= "\t<sioc:account_of>\n";
        $rdf .= "\t\t<foaf:Person>\n";
        if($this->_name) $rdf .= "\t\t\t<foaf:name>". $this->_name . "</foaf:name>\n";
        if($this->_email) { $rdf .= "\t\t\t<foaf:mbox_sha1sum>" . $this->_sha1 . "</foaf:mbox_sha1sum>\n"; }
        if($this->_foaf_url) { $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $this->_foaf_url ."\"/>\n"; }
        $rdf .= "\t\t</foaf:Person>\n";  
        $rdf .= "\t</sioc:account_of>\n";
        //if($this->_sioc_url) { $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"". $this->_sioc_url ."\"/>\n"; }
        $rdf .= "</sioc:User>\n";    

        return $rdf;
    }
}

/**
 * SIOC::DokuWikiContainer object
 *
 * Contains information about a wiki container
 */
class SIOCDokuWikiContainer extends SIOCObject
{

    var $_type = 'sioc:Container';

    var $_id = null;
    var $_url = null;
    var $_posts = array();
    var $_subcontainers = array();
    var $_title = null;

    function SIOCDokuWikiContainer ($id, $url)
    {
        $this->_id = $id;
        $this->_url = $url;
    }
    
    function isWiki() { $this->_type = 'sioct:Wiki'; }
    function addArticles($posts) { $this->_posts = $posts; }
    function addContainers($containers) { $this->_subcontainers = $containers; }
    function addTitle($title) { $this->_title = $title; }

    function getContent( &$exp ) {
        $rdf = '<'.$this->_type." rdf:about=\"" . clean($this->_url) . "\" >\n";
        
        if ($this->_title)
        {
            $rdf .= "\t<sioc:name>".htmlentities($this->_title)."</sioc:name>\n";
        }

        foreach($this->_posts as $article)
        {
            // TODO: test permission before?
            $rdf .= "\t<sioc:container_of>\n";
            // TODO: inluding title $rdf .= "\t\t<sioc:Post rdf:about=\"".."\" dc:title=\"".."\">\n";
            $rdf .= "\t\t<sioc:Post rdf:about=\"".getAbsUrl(wl($article['id']))."\">\n";
            $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"".$exp->siocURL('post',$article['id'])."\"/>\n";
            $rdf .= "\t\t</sioc:Post>\n";
            $rdf .= "\t</sioc:container_of>\n";
        }
        
        //print_r($this->_subcontainers); die();
        // TODO: Container 1 -> * Container ???
        foreach($this->_subcontainers as $container)
        {
            $rdf .= "\t<sioc:container_of>\n";
            // TODO: inluding title $rdf .= "\t\t<sioc:Post rdf:about=\"".."\" dc:title=\"".."\">\n";
            $rdf .= "\t\t<sioc:Container rdf:about=\"".getAbsUrl(wl($container['id']))."\">\n";
            $rdf .= "\t\t\t<rdfs:seeAlso rdf:resource=\"".$exp->siocURL('container',$container['id'])."\"/>\n";
            $rdf .= "\t\t</sioc:Container>\n";
            $rdf .= "\t</sioc:container_of>\n";
        }

        $rdf .= "</".$this->_type.">\n";
        return $rdf;
    }

}

