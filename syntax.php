<?php
/**
 * Plugin Now: Inserts Talis code for SIOC comments widget
 * 
 * DEPRECATED!!!
 */
 
// must be run within DokuWiki
if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 */
class syntax_plugin_dokusioc extends DokuWiki_Syntax_Plugin {
 
    function getInfo(){
      return array(
        'author' => 'Michael Haschke',
        'email'  => 'haschek@eye48.com',
        'date'   => '2009-02-16',
        'name'   => 'SIOC comments widget',
        'desc'   => 'Only a stub for further development. It is doing nothing!',
        'url'    => 'http://eye48.com/go/dokusioc',
      );
    }
 
    function getType() { return 'substition'; }
    function getSort() { return 999; }
    function connectTo($mode) { $this->Lexer->addSpecialPattern('\[SIOCCOMMENTS\]',$mode,'plugin_dokusioc'); }
    function handle($match, $state, $pos, &$handler){ return array($match, $state, $pos); }
    function render($mode, &$renderer, $data) {
    /*
      if($mode == 'xhtml'){
          $renderer->doc .= '<div class="sioc-has_reply"></div><script type="text/javascript" charset="utf-8" src="http://n2.talis.com/svn/playground/kwijibo/javascript/sioc-comments/bundle.js"></script>';
          return true;
      }
      */
      return false;
    }
}

