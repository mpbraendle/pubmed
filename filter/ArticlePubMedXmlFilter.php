<?php

/**
 * @file plugins/importexport/pubmed/filter/ArticlePubMedXmlFilter.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ArticlePubMedXmlFilter
 *
 * @brief Class that converts a Article to a PubMed XML document.
 */

namespace APP\plugins\importexport\pubmed\filter;

use APP\author\Author;
use APP\core\Application;
use APP\decision\Decision;
use APP\facades\Repo;
use APP\issue\Issue;
use APP\journal\Journal;
use APP\journal\JournalDAO;
use APP\submission\Submission;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\filter\PersistableFilter;
use PKP\i18n\LocaleConversion;

class ArticlePubMedXmlFilter extends PersistableFilter
{
    //
    // Implement abstract methods from SubmissionPubMedXmlFilter
    //
    /**
     * Get the representation export filter group name
     *
     * @return string
     */
    public function getRepresentationExportFilterGroupName()
    {
        return 'article-galley=>pubmed-xml';
    }

    //
    // Implement template methods from Filter
    //
    /**
     * @see Filter::process()
     *
     * @param array $submissions Array of submissions
     *
     * @return \DOMDocument
     */
    public function &process(&$submissions)
    {
        // Create the XML document
        $implementation = new \DOMImplementation();
        $dtd = $implementation->createDocumentType('ArticleSet', '-//NLM//DTD PubMed 2.0//EN', 'http://www.ncbi.nlm.nih.gov/entrez/query/static/PubMed.dtd');
        $doc = $implementation->createDocument('', '', $dtd);
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $journalDao = DAORegistry::getDAO('JournalDAO'); /** @var JournalDAO $journalDao */
        $journal = null;

        /* CHIMIA CHANGE CHIMIA-26 2023/04/17/mb read filter definitions */
        include 'article_filters.php';
        include 'field_filters.php';
        include 'mappings.php';

        $rootNode = $doc->createElement('ArticleSet');

        /* CHIMIA CHANGE CHIMIA-26 2023/05/08/mb sort the submissions by page number */
        uasort($submissions,array('self','cmp_submission'));
        
        foreach ($submissions as $submission) {
            // Fetch associated objects
            if ($journal?->getId() !== $submission->getContextId()) {
                $journal = $journalDao->getById($submission->getContextId());
            }
            $issue = Repo::issue()->getBySubmissionId($submission->getId());
            $issue = $issue?->getJournalId() === $journal->getId() ? $issue : null;

            /* CHIMIA CHANGE CHIMIA-26 2022/07/18/mb filter by sections (positive) */
            $section_id = $submission->getSectionId();
            // comment next three lines for testing
            if (!in_array( $section_id, $filtersections )) {
                continue;
            }

            /* CHIMIA CHANGE CHIMIA-26 2022/12/12/mb filter by keywords (negative) */
            $publication = $submission->getCurrentPublication();
            $locale = $publication->getData('locale');

            $keyword_match = false;
            if (!empty($publication->getData('keywords'))) {
                $keywords = $publication->getLocalizedData('keywords');
                foreach ($keywords as $keyword) {
                    foreach ($filterkeywords as $fkw) {
                        if (preg_match($fkw, $keyword)) {
                            $keyword_match = true;
                        }
                    }
                }
            }
            if ($keyword_match) {
                continue;
            }

            /* CHIMIA CHANGE CHIMIA-26 2022/12/19/mb filter by title terms (negative) */
            $title_match = false;
            $title = $publication->getLocalizedTitle($locale);
            foreach ($filtertitles as $ft) {
                if (preg_match($ft, $title)) {
                    $title_match = true;
                }
            }
            if ($title_match) {
                continue;
            }
            /* END CHIMIA CHANGE CHIMIA-26 */

            $articleNode = $doc->createElement('Article');
            /* CHIMIA CHANGE OJS-26 2024/07/12/mb pass journal mapping from mappings.php */
            $articleNode->appendChild($this->createJournalNode($doc, $journal, $issue, $submission, $journal2nlm));

            /* CHIMIA CHANGE CHIMIA-26 2023/04/19/mb extract language of article */
            /* $language2code imported from mappings.php */

            $language = 'en_US';
            if (!empty($publication->getData('languages'))) {
                $languages = $publication->getLocalizedData('languages');
                foreach ($languages as $language_code) {
                    $language = $language_code;
                }
            }

            $locale = $publication->getData('locale');
            if ($locale == 'en' || $language == 'en_US') {
                $articleNode->appendChild($doc->createElement('ArticleTitle'))->appendChild($doc->createTextNode($publication->getLocalizedTitle($locale, 'html')));
            } else {
                $articleNode->appendChild($doc->createElement('VernacularTitle'))->appendChild($doc->createTextNode($publication->getLocalizedTitle($locale, 'html')));
            }
            /* END CHIMIA CHANGE CHIMIA-26 */

            /* CHIMIA CHANGE CHIMIA-26 2022/12/12/mb LastPage is optional */
            $startPage = $publication->getStartingPage();
            $endPage = $publication->getEndingPage();
            
            if (isset($startPage) && $startPage !== '') {
                // We have a page range or e-location id
                $articleNode->appendChild($doc->createElement('FirstPage'))->appendChild($doc->createTextNode($startPage));
            }
            if (preg_match('/-/', $publication->getData('pages')) && isset($endPage) && $endPage !== '') {
                $articleNode->appendChild($doc->createElement('LastPage'))->appendChild($doc->createTextNode($endPage));
            }
            /* END CHIMIA CHANGE CHIMIA-26 */

            if ($doi = $publication->getStoredPubId('doi')) {
                $doiNode = $doc->createElement('ELocationID');
                $doiNode->appendChild($doc->createTextNode($doi));
                $doiNode->setAttribute('EIdType', 'doi');
                $articleNode->appendChild($doiNode);
            }

            /* CHIMIA CHANGE CHIMIA-26 2023/04/19/mb extract language of article */
            if (!empty($publication->getData('languages'))) {
                $languages = $publication->getLocalizedData('languages');
                foreach ($languages as $language) {
                    $articleNode->appendChild($doc->createElement('Language'))->appendChild($doc->createTextNode($language2code[$language]));
                }
            } elseif (!empty($locale)) {
                $lang_iso639_2b = LocaleConversion::get3LetterFrom2LetterIsoLanguage(substr($locale, 0, 2));
                $lang_iso639_1 = LocaleConversion::get2LetterFrom3LetterIsoLanguage($lang_iso639_2b);
                $articleNode->appendChild($doc->createElement('Language'))->appendChild($doc->createTextNode(strtoupper($lang_iso639_1)));
            } else {
                $articleNode->appendChild($doc->createElement('Language'))->appendChild($doc->createTextNode('EN'));
            }
            /* END CHIMIA CHANGE CHIMIA-26 */

            $authorListNode = $doc->createElement('AuthorList');
            foreach ($publication->getData('authors') ?? [] as $author) {
                $authorListNode->appendChild($this->generateAuthorNode($doc, $journal, $issue, $submission, $author));
            }
            $articleNode->appendChild($authorListNode);

            /* CHIMIA CHANGE CHIMIA-26 2024/01/12/mb Publication Type */
            /* section2pubtype imported from mappings.php */

            $pubtype = $section2pubtype[$section_id];

            /* exception rules for section 60 (Columns & Conference Reports) */
            if ($section_id == 60) {
                $article_title = $publication->getLocalizedTitle($locale);

                if (str_contains($article_title, 'Interview') || str_contains($article_title,'interview')) {
                    $pubtype = 'Interview';
                }

                $categoryIds = $publication->getData('categoryIds');

                foreach ($categoryIds as $categoryId) {
                    if ($categoryId == 12 || str_contains($article_title, 'Conference Report')) {
                        $pubtype = 'Congress';
                    }
                }
            }
            if (!isset($pubtype)) {
                $pubtype = 'Journal Article';
            }

            $publicationTypeNode = $doc->createElement('PublicationType');
            $publicationTypeNode->appendChild($doc->createTextNode($pubtype));
            $articleNode->appendChild($publicationTypeNode);
            /* END CHIMIA CHANGE CHIMIA-26 */

            /* UZH CHANGE OJS-238/CHIMIA-26 2024/01/19/mb add additional ArticleIdList> ArticleId */
            $doi = $publication->getStoredPubId('doi');
            $pubid = $publication->getStoredPubId('publisher-id');
            if ($doi || $pubid) {
                $articleIdListNode = $doc->createElement('ArticleIdList');
                if ($doi) {
                    $articleDoiNode = $doc->createElement('ArticleId');
                    $articleDoiNode->appendChild($doc->createTextNode($doi));
                    $articleDoiNode->setAttribute('IdType', 'doi');
                    $articleIdListNode->appendChild($articleDoiNode);
                }
                if ($pubid) {
                    $articleIdNode = $doc->createElement('ArticleId');
                    $articleIdNode->appendChild($doc->createTextNode($pubid));
                    $articleIdNode->setAttribute('IdType', 'pii');
                    $articleIdListNode->appendChild($articleIdNode);
                }
                $articleNode->appendChild($articleIdListNode);
            }
            /* END UZH CHANGE OJS-238/CHIMIA-26 */

            // History
            $historyNode = $doc->createElement('History');
            $historyNode->appendChild($this->generatePubDateDom($doc, $submission->getDateSubmitted(), 'received'));

            $editorDecision = Repo::decision()->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->getMany()
                ->first(fn (Decision $decision, $key) => $decision->getData('decision') === Decision::ACCEPT);

            if ($editorDecision) {
                $historyNode->appendChild($this->generatePubDateDom($doc, $editorDecision->getData('dateDecided'), 'accepted'));
            }
            $articleNode->appendChild($historyNode);

            // FIXME: Revision dates

            if ($abstract = PKPString::html2text($publication->getLocalizedData('abstract', $locale))) {
                /* CHIMIA CHANGE CHIMIA-26 2023/04/19/mb filter for "no abstract", language selection */

                $abstract_match = false;

                foreach ($abstractfilters as $abf) {
                    if (preg_match($abf, $abstract)) {
                        $abstract_match = true;
                    }
                }

                if ($abstract_match === false) {
                    if ($language === 'en_US') {
                        $articleNode->appendChild($doc->createElement('Abstract'))->appendChild($doc->createTextNode($abstract));
                    } else {
                        $otherAbstractNode = $doc->createElement('OtherAbstract');
                        $langattr = strtolower($language2code[$language]);
                        $otherAbstractNode->setAttribute('Language',$langattr);
                        $otherAbstractNode->appendChild($doc->createTextNode($abstract));
                        $articleNode->appendChild($otherAbstractNode);
                    }
                }
                /* END CHIMIA CHANGE CHIMIA-26 */
            }

            /* CHIMIA CHANGE CHIMIA-26 2022/09/12/mb CopyrightInformation */
            $licenseUrl = $publication->getData('licenseUrl');
            $license = htmlspecialchars(strip_tags(Application::get()->getCCLicenseBadge($licenseUrl)), ENT_COMPAT, 'UTF-8');
            $copyrightYear = $publication->getData('copyrightYear');
            $copyrightInformation = 'Copyright ' . $copyrightYear;
            if (!empty($publication->getData('copyrightHolder'))) {
                $copyrightHolder = $publication->getLocalizedData('copyrightHolder');
            } else {
                $journalTitleInternal = $journal->getName($journal->getPrimaryLocale());
                $copyrightHolder = $journal2nlm[$journalTitleInternal]['publishername'];
            }
            $copyrightInformation .= ' ' .$copyrightHolder;
            $copyrightInformation .= '. License: ' . $license;
            $copyrightNode = $doc->createElement('CopyrightInformation');
            $copyrightNode->appendChild($doc->createTextNode($copyrightInformation));
            $articleNode->appendChild($copyrightNode);
            /* END CHIMIA CHANGE CHIMIA-26 */

            /* CHIMIA CHANGE CHIMIA-26 2023/07/03/mb Funders and Grants */
            $funderDAO = DAORegistry::getDAO('FunderDAO');
            $funderAwardDAO = DAORegistry::getDAO('FunderAwardDAO');

            $funders = $funderDAO->getBySubmissionId($submission->getId());
            $grants = array();

            while ($funder = $funders->next()) {
                $funderId = $funder->getId();
                $funderAwards = $funderAwardDAO->getFunderAwardNumbersByFunderId($funderId);

                $fundernameCrossref = $funder->getFunderName();
                $fundername = $fundernameCrossref;
                $fundercountry = '';

                if (array_key_exists($fundernameCrossref, $crossreffunder2pubmed)) {
                    $fundername = $crossreffunder2pubmed[$fundernameCrossref]['fundername'];
                    $fundercountry = $crossreffunder2pubmed[$fundernameCrossref]['fundercountry'];
                }

                foreach ($funderAwards as $funderAward) {
                    $grants[$funderAward] = array(
                        'funderName' => $fundername,
                        'funderAward' => $funderAward,
                        'funderCountry' => $fundercountry
                    );
                }
            }
            /* END CHIMIA CHANGE CHIMIA-26 */

            /* CHIMIA CHANGE CHIMIA-26 2022/09/05/mb Keywords */
            if (!empty($publication->getData('keywords')) || !empty($grants)) {
                $objectListNode = $doc->createElement('ObjectList');
                $keywords = $publication->getLocalizedData('keywords');
                $keyword_set = false;
                foreach ($keywords as $keyword) {
                    if (!empty($keyword)) {
                        $keyword_set = true;
                    }
                }
                if ($keyword_set) {
                    foreach ($keywords as $keyword) {
                        $objectNode = $doc->createElement('Object');
                        $objectNode->setAttribute('Type','keyword');
                        $paramNode = $doc->createElement('Param');
                        $paramNode->setAttribute('Name','value');
                        $paramNode->appendChild($doc->createTextNode($keyword));
                        $objectNode->appendChild($paramNode);
                        $objectListNode->appendChild($objectNode);
                    }
                }
                if (!empty($grants)) {
                    foreach ($grants as $grant) {
                        $objectNode = $doc->createElement('Object');
                        $objectNode->setAttribute('Type','grant');
                        $paramNode = $doc->createElement('Param');
                        $paramNode->setAttribute('Name','id');
                        $paramNode->appendChild($doc->createTextNode($grant['funderAward']));
                        $objectNode->appendChild($paramNode);
                        $paramNode = $doc->createElement('Param');
                        $paramNode->setAttribute('Name','grantor');
                        $paramNode->appendChild($doc->createTextNode($grant['funderName']));
                        $objectNode->appendChild($paramNode);
                        if ($grant['funderCountry'] !== '') {
                            $paramNode = $doc->createElement('Param');
                            $paramNode->setAttribute('Name','country');
                            $paramNode->appendChild($doc->createTextNode($grant['funderCountry']));
                            $objectNode->appendChild($paramNode);
                        }
                        $objectListNode->appendChild($objectNode);
                    }
                }

                if ($keyword_set || !empty($grants)) {
                    $articleNode->appendChild($objectListNode);
                }
            }
            /* END CHIMIA CHANGE CHIMIA-26 */

            /* CHIMIA/UZH CHANGE CHIMIA-26/OJS-238 2024/02/22/mb References */
            if ($publication->getData('citationsRaw')) {
                $citationDao = DAORegistry::getDAO('CitationDAO');
                $crossrefReferenceLinkingPlugin = PluginRegistry::getPlugin('generic', 'crossrefreferencelinkingplugin');
                $doiSettingName = $crossrefReferenceLinkingPlugin->getCitationDoiSettingName();
                $parsedCitations = $citationDao->getByPublicationId($publication->getId())->toAssociativeArray();
                if ($parsedCitations) {
                    $referenceListNode = $doc->createElement('ReferenceList');
                    foreach ($parsedCitations as $citation) {
                        $rawCitation = $citation->getRawCitation();
                        if (!empty($rawCitation)) {
                            $referenceNode = $doc->createElement('Reference');
                            $citationNode = $doc->createElement('Citation');
                            $citationNode->appendChild($doc->createTextNode(htmlspecialchars($rawCitation, ENT_COMPAT, 'UTF-8')));
                            $referenceNode->appendChild($citationNode);
                            // include Crossref DOI if it exists
                            $doi = $citation->getData($doiSettingName);
                            if ($doi) {
                                $referenceArticleIdListNode = $doc->createElement('ArticleIdList');
                                $referenceArticleIdNode = $doc->createElement('ArticleId');
                                $referenceArticleIdNode->setAttribute('IdType','doi');
                                $referenceArticleIdNode->appendChild($doc->createTextNode(htmlspecialchars($doi, ENT_COMPAT, 'UTF-8')));
                                $referenceArticleIdListNode->appendChild($referenceArticleIdNode);
                                $referenceNode->appendChild($referenceArticleIdListNode);
                            }
                            $referenceListNode->appendChild($referenceNode);
                        }
                    }
                    $articleNode->appendChild($referenceListNode);
                }
            }
            /* END CHIMIA/UZH CHANGE CHIMIA-26/OJS-238 */

            $rootNode->appendChild($articleNode);
        }
        $doc->appendChild($rootNode);
        return $doc;
    }

    /**
     * Construct and return a Journal element.
     *
     * @param \DOMDocument $doc
     * @param Journal $journal
     * @param Issue $issue
     * @param Submission $submission
     */
    public function createJournalNode($doc, $journal, $issue, $submission, $nlmmapping = null)
    {
        $journalNode = $doc->createElement('Journal');

        /* CHIMIA CHANGE CHIMIA-26 2024/07/12/mb map journal title to NLM journal title if available */
        $journalTitleInternal = $journal->getName($journal->getPrimaryLocale());
        $publisherNameNode = $doc->createElement('PublisherName');
        $journalTitleNode = $doc->createElement('JournalTitle');

        if (!is_null($nlmmapping) && array_key_exists($journalTitleInternal, $nlmmapping)) {
            $journalTitle = $nlmmapping[$journalTitleInternal]['journaltitle'];
            $publisherName = $nlmmapping[$journalTitleInternal]['publishername'];
        } else {
            $journalTitle = $journalTitleInternal;
            $publisherName = $journal->getData('publisherInstitution');
        }

        $publisherNameNode->appendChild($doc->createTextNode($publisherName));
        $journalNode->appendChild($publisherNameNode);

        $journalTitleNode->appendChild($doc->createTextNode($journalTitle));
        $journalNode->appendChild($journalTitleNode);
        /* END CHIMIA CHANGE CHIMIA-26 */

        // check various ISSN fields to create the ISSN tag
        if ($journal->getData('printIssn') != '') {
            $issn = $journal->getData('printIssn');
        } elseif ($journal->getData('issn') != '') {
            $issn = $journal->getData('issn');
        } elseif ($journal->getData('onlineIssn') != '') {
            $issn = $journal->getData('onlineIssn');
        } else {
            $issn = '';
        }
        if ($issn != '') {
            $journalNode->appendChild($doc->createElement('Issn', $issn));
        }

        if ($issue && $issue->getShowVolume()) {
            $journalNode->appendChild($doc->createElement('Volume'))->appendChild($doc->createTextNode($issue->getVolume()));
        }
        if ($issue && $issue->getShowNumber()) {
            $journalNode->appendChild($doc->createElement('Issue'))->appendChild($doc->createTextNode($issue->getNumber()));
        }

        $datePublished = $submission->getCurrentPublication()?->getData('datePublished')
            ?: $issue?->getDatePublished();
        if ($datePublished) {
            $journalNode->appendChild($this->generatePubDateDom($doc, $datePublished, 'epublish'));
        }

        return $journalNode;
    }

    /**
     * Generate and return an author node representing the supplied author.
     *
     * @param \DOMDocument $doc
     * @param Journal $journal
     * @param Issue $issue
     * @param Submission $submission
     * @param Author $author
     *
     * @return \DOMElement
     */
    public function generateAuthorNode($doc, $journal, $issue, $submission, $author)
    {
        $authorElement = $doc->createElement('Author');

        if (empty($author->getLocalizedFamilyName())) {
            $authorElement->appendChild($node = $doc->createElement('FirstName'));
            $node->setAttribute('EmptyYN', 'Y');
            $authorElement->appendChild($doc->createElement('LastName'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedGivenName())));
        } else {
            $authorElement->appendChild($doc->createElement('FirstName'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedGivenName())));
            $authorElement->appendChild($doc->createElement('LastName'))->appendChild($doc->createTextNode(ucfirst($author->getLocalizedFamilyName())));
        }
        
        /* CHIMIA CHANGE CHIMIA-26 2022/09/12/mb, 2023/07/10/mb affiliation */
        $affiliation = $author->getLocalizedAffiliation();
        $email_pos = strpos($affiliation, ';, Email:');
        if ($email_pos !== false) {
            $affiliation = substr($affiliation,0,$email_pos);
        }

        $affiliations = explode("; ", $affiliation);
        $affilcount = count($affiliations);
        if ($affilcount > 1) {
            $affc = 0;
            foreach ($affiliations as $affil) {
                $affc++;
                if (!empty($author->getEmail()) && $affc === 1) {
                    $authorElement->appendChild($doc->createElement('AffiliationInfo'))->appendChild($doc->createElement('Affiliation'))->appendChild($doc->createTextNode($affil . '. ' . $author->getEmail()));
                } else {
                    $authorElement->appendChild($doc->createElement('AffiliationInfo'))->appendChild($doc->createElement('Affiliation'))->appendChild($doc->createTextNode($affil));
                }
            }
        } elseif ($affilcount === 1) {
             if (empty($author->getEmail())) {
                $authorElement->appendChild($doc->createElement('Affiliation'))->appendChild($doc->createTextNode($affiliation));
            } else {
                 $authorElement->appendChild($doc->createElement('Affiliation'))->appendChild($doc->createTextNode($affiliation . '. ' . $author->getEmail()));
            }
        } else {
        }
        /* END CHIMIA CHANGE CHIMIA-26 */

        /* CHIMIA CHANGE CHIMIA-26 2022/09/05/mb ORCID */
        if (!empty($author->getOrcid())) {
            $orcid = str_replace('https://orcid.org/','',$author->getOrcid());
            $identifierNode = $doc->createElement('Identifier');
            $identifierNode->setAttribute('Source','ORCID');
            $identifierNode->appendChild($doc->createTextNode($orcid));
            $authorElement->appendChild($identifierNode);
        }
        /* END CHIMIA CHANGE CHIMIA-26 */

        return $authorElement;
    }

    /**
     * Generate and return a date element per the PubMed standard.
     *
     * @param \DOMDocument $doc
     * @param string $pubDate
     * @param string $pubStatus
     *
     * @return \DOMElement
     */
    public function generatePubDateDom($doc, $pubDate, $pubStatus)
    {
        $pubDateNode = $doc->createElement('PubDate');
        $pubDateNode->setAttribute('PubStatus', $pubStatus);

        $pubDateNode->appendChild($doc->createElement('Year', date('Y', strtotime($pubDate))));
        $pubDateNode->appendChild($doc->createElement('Month', date('m', strtotime($pubDate))));
        $pubDateNode->appendChild($doc->createElement('Day', date('d', strtotime($pubDate))));

        return $pubDateNode;
    }

    /* CHIMIA CHANGE CHIMIA-26 2023/05/08 sort function */
    public function cmp_submission($a, $b) {
        $pub_a = $a->getCurrentPublication();
        $pub_b = $b->getCurrentPublication();
        $page_a = $pub_a->getStartingPage();
        $page_b = $pub_b->getStartingPage();

        if ($page_a == $page_b) {
            return 0;
        }
        return ($page_a < $page_b) ? -1 : 1;
    }
    /* END CHIMIA CHANGE CHIMIA-26 */

}
