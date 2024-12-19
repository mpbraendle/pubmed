# pubmed
PubMed export plugin for OJS 3.4

## Original Version
OJS PubMed Export Plugin
Version: 1.0.1
Release date: May 10, 2006
Author: MJ Suhonos <mj@robotninja.com>


### About
This export plugin for OJS 3.4 creates bibliographic information for articles or issues in the PubMed XML tagged format for indexing in NLM PubMed/MEDLINE.
Details on the XML format and data requirements are available at: https://www.ncbi.nlm.nih.gov/books/NBK3828/

### Updates and Improvements
by Martin Brändle (Swiss Chemical Society and University of Zurich)
Some updates and improves were introduced due to requirements by the NIH review team. These updates should help to minimize manual post-editing of the export PubMed XML.
- option to map the a different NLM journal title and publisher than the one used by OJS due to historic indexing by PubMed and continuation of this old title
- sort the submissions of an issue by page number
- option to filter articles by sections - some sections may not be required by NIH terms
- option to filter articles by keywords - some articles may not be required by NIH terms
- option to filter articles by title terms - some articles may not be required by NIH terms
- use language of the article
- PubMed XML LastPage element is optional if article has just one page
- option of mapping sections to PubMed XML PublicationType
- added ArticleIdList> ArticleId element for DOI
- use PubMed XML OtherAbstract element for non-english articles
- include PubMed XML CopyrightInformation element
- include Funders and Grants data into PubMed XML Object elements
- include References
- include affiliation data, treat multiple affilations per author
- include ORCID data

For the mappings, see Configuration below.


### TODO
- PubMed COIStatement (still needs to be inserted manually).


### License
This plugin is licensed under the GNU General Public License v3. See the file LICENSE for the complete terms of this license.

### System Requirements
Same requirements as the OJS 3.4.0-x core.

### Installation
To install the plugin:
 - copy the pubmed folder into OJS/plugins/importexport

The export functionality can then be accessed through: 
 - Tools > tab Import/Export > PubMed XML Export Plugin

### Configuration
For the different mappings, there are three files in the pubmed/filter directory. Please edit to your requirements.

mappings.php 
contains mappings for 
- NLM journal and publisher name
- section to publication type
- OJS languages to NLM language codes
- Crossref funder names to NLM funder names and countries

article_filters.php
contains various filters for sections, keywords, and title terms

field_filters.php
contains filters for empty/missing abstract


### Contributors
MJ Suhonos <mj@robotninja.com>
MP Brändle <https://github.com/mpbraendle>