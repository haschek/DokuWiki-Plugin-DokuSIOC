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
    var $_contributors = array();
    var $_created = null;
    var $_modified = null;
    //var $_topics = array();
    var $_links = array();
    var $_backlinks = array();
    //var $_ext_links = array();
    var $_previous_version = null;
    var $_next_version = null;
    var $_latest_version = false; // show latest version
    //var $_has_discussion = null;
    var $_has_container = null;
    var $_has_space = null;
    var $_content = null;
    var $_content_encoded = null;
    var $_is_creator = false;

    function SIOCDokuWikiArticle($id, $url, $subject, $content)
    {
        $this->_id = $id;
        $this->_url = $url;
        $this->_subject = $subject;
        $this->_content = $content;
        return;
    }
    
    function addCreated($created) { $this->_created = $created; }
    function addModified($modified) { $this->_modified = $modified; }
    function addCreator($creator) { $this->_creator = $creator; }
    function addContributors($contributors) { $this->_contributors = $contributors; }
    function isCreator() { $this->_is_creator = true; }
    function addLinks($links) { if (is_array($links) && count($links)>0) $this->_links = $links; }
    function addBacklinks($links) { $this->_backlinks = $links; }
    //function addLinksExtern($links) { if (is_array($links) && count($links)>0) $this->_ext_links = $links; }
    function addVersionPrevious($rev) { $this->_previous_version = $rev; }
    function addVersionNext($rev) { $this->_next_version = $rev; }
    function addVersionLatest() { $this->_latest_version = true; }
    function addContentEncoded($encoded) { $this->_content_encoded = $encoded; }
    function addContainer($id) { $this->_has_container = $id; }
    function addSite($url) { $this->_has_space = $url; }
    

    function getContent( &$exp )
    {
        $rdf = '<'.$this->_type." rdf:about=\"" . clean($this->_url, true) . "\">\n";
        if ($this->_subject)
        {
            $rdf .= "\t<dc:title>" . clean($this->_subject) . "</dc:title>\n";
            // if(strcmp($this->_has_container, 'http://en.wikipedia.org')===0)
            //    $rdf .= "\t<foaf:primaryTopic rdf:resource=\"".clean('http://dbpedia.org/resource/'.$this->_subject)."\"/>\n";
        }

        $creator_name = null;

        if (count($this->_contributors) > 0)
        {
            foreach($this->_contributors as $cont_id => $cont_name)
            {
                if(!isset($this->_creator['sioc:modifier']) || ($this->_creator['sioc:modifier'] != $cont_id))
                    $rdf .= "\t<sioc:has_modifier rdf:resource=\"".normalizeUri($exp->siocURL('user', $cont_id))."\" rdfs:label=\"".clean($cont_name)."\"/>\n";
            }
            
            if (isset($this->_contributors[$this->_creator['sioc:modifier']]))
            {
                $creator_name = 'rdfs:label="'.clean($this->_contributors[$this->_creator['sioc:modifier']]).'"';
            }
        }

        if (is_array($this->_creator))
        {
            // if ($this->_creator['foaf:maker'])
            //     $rdf .= "\t<foaf:maker rdf:resource=\"".clean($this->_creator['foaf:maker'])."\"/>\n";
            if ($this->_creator['sioc:modifier'])
            {
                if ($this->_is_creator === false) $rdf .= "\t<sioc:has_modifier rdf:resource=\"".normalizeUri($exp->siocURL('user', $this->_creator['sioc:modifier']))."\" $creator_name/>\n";
                if ($this->_is_creator === true) $rdf .= "\t<sioc:has_creator rdf:resource=\"".normalizeUri($exp->siocURL('user', $this->_creator['sioc:modifier']))."\" $creator_name/>\n";
            }
        }
        
        if ($this->_created)
        {
            $rdf .= "\t<dcterms:created>" . $this->_created . "</dcterms:created>\n";
        }
        
        if ($this->_modified)
        {
            $rdf .= "\t<dcterms:modified>" . $this->_modified . "</dcterms:modified>\n";
        }
        
        if ($this->_has_space)
        {
            $rdf .= "\t<sioc:has_space rdf:resource=\"".clean($this->_has_space, true)."\" />\n";
            // TODO: rdfs:label
        }
        
        if($this->_has_container)
        {
            $rdf .= "\t<sioc:has_container rdf:resource=\"".normalizeUri($exp->siocURL('container', $this->_has_container))."\" />\n";
            // TODO: rdfs:label
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
                    $rdf .= "\t<sioc:links_to rdf:resource=\"". normalizeUri($exp->siocURL('post', $link_id)) ."\"/>\n";
                    // TODO: rdfs:label
                }
            }
        }
        
        if (count($this->_backlinks)>0)
        {
            foreach($this->_backlinks as $link_id)
            {
                if (!isHiddenPage($link_id))
                {
                    $rdf .= "\t<dcterms:isReferencedBy rdf:resource=\"". normalizeUri($exp->siocURL('post', $link_id)) ."\"/>\n";
                    // TODO: rdfs:label
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
                $rdf .= "\t<sioc:previous_version rdf:resource=\"". normalizeUri($exp->siocURL('post', $this->_id.$exp->_urlseparator.'rev'.$exp->_urlequal.$this->_previous_version)) ."\"/>\n";
                // TODO: rdfs:label

                /* If there is support for inference and transitivity the following is not needed */
                $rdf .= "\t<sioc:earlier_version rdf:resource=\"". normalizeUri($exp->siocURL('post', $this->_id.$exp->_urlseparator.'rev'.$exp->_urlequal.$this->_previous_version)) ."\"/>\n";
                // TODO: rdfs:label

        }

        if($this->_next_version) {
                $rdf .= "\t<sioc:next_version rdf:resource=\"". normalizeUri($exp->siocURL('post', $this->_id.$exp->_urlseparator.'rev'.$exp->_urlequal.$this->_next_version)) ."\"/>\n";
                // TODO: rdfs:label
                
                /* If there is support for inference and transitivity the following is not needed */
                $rdf .= "\t<sioc:later_version rdf:resource=\"". normalizeUri($exp->siocURL('post', $this->_id.$exp->_urlseparator.'rev'.$exp->_urlequal.$this->_next_version)) ."\"/>\n";
                // TODO: rdfs:label
        }

        if($this->_latest_version) {
                $rdf .= "\t<sioc:latest_version rdf:resource=\"". normalizeUri($exp->siocURL('post', $this->_id)) ."\"/>\n";
                // TODO: rdfs:label
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
        $rdf = "<sioc:User rdf:about=\"" . clean($this->_url, true) ."\">\n";
        if($this->_nick) $rdf .= "\t<sioc:name>" . clean($this->_nick) . "</sioc:name>\n";
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
        if($this->_name) $rdf .= "\t\t\t<foaf:name>". clean($this->_name) . "</foaf:name>\n";
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
    var $_has_parent = null;
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
    function addParent($id) { $this->_has_parent = $id; }

    function getContent( &$exp ) {
        $rdf = '<'.$this->_type." rdf:about=\"" . normalizeUri(clean($this->_url, true)) . "\" >\n";
        
        if ($this->_title)
        {
            $rdf .= "\t<sioc:name>".clean($this->_title)."</sioc:name>\n";
        }

        if($this->_has_parent)
        {
            $rdf .= "\t<sioc:has_parent rdf:resource=\"".normalizeUri($exp->siocURL('container', $this->_has_parent))."\" />\n";
            // TODO: rdfs:label
        }
        
        foreach($this->_posts as $article)
        {
            // TODO: test permission before?
            $rdf .= "\t<sioc:container_of rdf:resource=\"".normalizeUri($exp->siocURL('post',$article['id']))."\"/>\n";
            // TODO: inluding title/name
        }
        
        foreach($this->_subcontainers as $container)
        {
            $rdf .= "\t<sioc:parent_of rdf:resource=\"".normalizeUri($exp->siocURL('container',$container['id']))."\"/>\n";
            // TODO: inluding title/name
        }

        $rdf .= "</".$this->_type.">\n";
        return $rdf;
    }

}

