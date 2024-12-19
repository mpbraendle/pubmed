<?php

/**
 * @file plugins/importexport/pubmed/filter/mappings.php
 *
 * Copyright (c) 2024 University of Zurich/Swiss Chemical Society
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticlePubMedXmlFilter
 * @ingroup plugins_importexport_pubmed
 *
 * @brief filter definitions
 */

/* Mapping from OJS journal title to NLM journal title/publisher name */
$journal2nlm = array(
    "CHIMIA" => array(
        "journaltitle" => "Chimia (Aarau)",
        "publishername" => "Swiss Chemical Society",
    )
);

/* Mapping from section to publication type */
$section2pubtype = array(
    "58" => "Editorial",
    "59" => "Journal Article",
    "60" => "Journal Article",
    "66" => "Journal Article",
    "74" => "Published Erratum",
    "75" => "Published Erratum",
);

/* Mapping from OJS language to PubMed language codes */
$language2code = array(
    'de' => 'DE',
    'DE' => 'DE',
    'de_DE' => 'DE',
    'de_CH' => 'DE',
    'en' => 'EN',
    'EN' => 'EN',
    'en_GB' => 'EN',
    'en_US' => 'EN',
    'es' => 'ES',
    'ES' => 'ES',
    'es_ES' => 'ES',
    'fr' => 'FR',
    'FR' => 'FR',
    'fr_CA' => 'FR',
    'fr_FR' => 'FR',
    'it' => 'IT',
    'IT' => 'IT',
    'it_IT' => 'IT',
);

/* Funder name mappings from Crossref to PubMed */
/* See https://wayback.archive-it.org/org-350/20210414192512/https://www.nlm.nih.gov/bsd/grant_acronym.html */
$crossreffunder2pubmed = array(
    'Schweizerischer Nationalfonds zur Förderung der Wissenschaftlichen Forschung' => array(
        'fundername' => 'Swiss National Science Foundation',
        'fundercountry' => 'Switzerland'
    )
);

?>
