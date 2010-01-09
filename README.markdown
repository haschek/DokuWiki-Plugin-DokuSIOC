DokuSIOC -- a SIOC plugin for DokuWiki
================================================================================

DokuSIOC integrates the [SIOC ontology] [1] within [DokuWiki] [2] and provides
alternate RDF/XML views of the wiki documents.

[1]: http://sioc-project.org/ontology
[2]: http://dokuwiki.org/

Web resources
--------------------------------------------------------------------------------

  * [Official website] [3] 
  * [DokuWiki page] [4]
  * [Repository & Issue tracker] [5]

[3]: http://eye48.com/go/dokusioc
[4]: http://www.dokuwiki.org/plugin:dokusioc
[5]: http://github.com/haschek/DokuWiki-Plugin-DokuSIOC

License
--------------------------------------------------------------------------------

The SIOC plugin for DokuWiki (DokuSIOC) is released open-source under the [GNU
General Public License 2.0] [GPLv2] licence.

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, version 2 of the License.

This program is distributed in the hope that it will be useful, but WITHOUT
ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

[GPLv2]: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Features
--------------------------------------------------------------------------------

  * Creates meta descriptions for sioc:User, sioct:WikiArticle and
    sioc:Container (incl. sioct:Wiki) and it includes information about
    next/previous versions, creator/modifier, contributors, date, content,
    container and inner wiki links between the articles.
  * It adds a link to those meta descriptions in the HTML header.
  * Pings [pingthesemanticweb.com] [6] for new/edited content
  * Linked Data
  * Content Negotiation for application/rdf+xml requests
  * Possibility to hide RDF content from search engines

[6]: http://pingthesemanticweb.com/
