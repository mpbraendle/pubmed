<?php

/**
 * @file plugins/importexport/pubmed/filter/article_filters.php
 *
 * Copyright (c) 2022 Swiss Chemical Society
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticlePubMedXmlFilter
 * @ingroup plugins_importexport_pubmed
 *
 * @brief filter definitions 
 */

/* CHIMIA CHANGE CHIMIA-26 2022/07/18/mb filter by sections (positive) */
$filtersections = array( 58, 59, 66, 74, 75 );

/* CHIMIA CHANGE CHIMIA-26 2022/12/12/mb filter by keywords (negative) */
$filterkeywords = array( '/book\sreview/i', '/obituary/i' ); 

/* CHIMIA CHANGE CHIMIA-26 2022/12/19/mb filter by title terms (negative) */
$filtertitles = array( '/book\sreview/i', '/obituary/i' ); 

?>
