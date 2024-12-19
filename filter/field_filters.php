<?php

/**
 * @file plugins/importexport/pubmed/filter/field_filters.php
 *
 * Copyright (c) 2023 Swiss Chemical Society
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticlePubMedXmlFilter
 * @ingroup plugins_importexport_pubmed
 *
 * @brief filter definitions 
 */

/* CHIMIA CHANGE CHIMIA-26 2023/04/17/mb filter for empty abstract (negative filter) */
$abstractfilters = array( '/no\sabstract/i', '/abstract\smissing/i', '/missing\s/abstract/i' );

?>
