<?php

use App\Entity\Template;
use App\Entity\Quote;
use App\Security\User;
use App\Repository\DestinationRepository;
use App\Repository\QuoteRepository;
use App\Repository\SiteRepository;


class TemplateManager{
    
    private $siteRepository;
    private $destinationRepository;
    private $quoteRepository;
    
    public function __construct(SiteRepository $siteRepository, DestinationRepository $destinationRepository, QuoteRepository $quoteRepository){
        $this->siteRepository = $siteRepository;
        $this->destinationRepository = $destinationRepository;
        $this->quoteRepository = $quoteRepository;
    }
    
    public function getTemplateComputed(Template $tpl, array $data)
    {
        if (!$tpl) {
            throw new \RuntimeException('no tpl given');
        }

        $replaced = clone($tpl);
        $replaced->subject = $this->computeText($replaced->subject, $data);
        $replaced->content = $this->computeText($replaced->content, $data);

        return $replaced;
    }

    private function computeText($text, array $data)
    {
        $APPLICATION_CONTEXT = ApplicationContext::getInstance();

        $quote = (isset($data['quote']) and $data['quote'] instanceof Quote) ? $data['quote'] : null;

        if ($quote)
        {
            $_quoteFromRepository = $this->quoteRepository->getInstance()->getById($quote->id);
            $usefulObject = $this->siteRepository->getById($quote->siteId);
            $destinationOfQuote = $this->destinationRepository->getById($quote->destinationId);

            if(strpos($text, '[quote:destination_link]') !== false){
                $destination = $this->destinationRepository->getById($quote->destinationId);
            }

            $containsSummaryHtml = strpos($text, '[quote:summary_html]');
            $containsSummary     = strpos($text, '[quote:summary]');

            if ($containsSummaryHtml !== false || $containsSummary !== false) {
                if ($containsSummaryHtml !== false) {
                    $text = str_replace(
                        '[quote:summary_html]',
                        Quote::renderHtml($_quoteFromRepository),
                        $text
                    );
                }
                if ($containsSummary !== false) {
                    $text = str_replace(
                        '[quote:summary]',
                        Quote::renderText($_quoteFromRepository),
                        $text
                    );
                }
            }

            (strpos($text, '[quote:destination_name]') !== false) and $text = str_replace('[quote:destination_name]',$destinationOfQuote->countryName,$text);
        }

        if (isset($destination))
            $text = str_replace('[quote:destination_link]', $usefulObject->url . '/' . $destination->countryName . '/quote/' . $_quoteFromRepository->id, $text);
        else
            $text = str_replace('[quote:destination_link]', '', $text);

        /*
         * USER
         * [user:*]
         */
        $_user  = (isset($data['user'])  and ($data['user']  instanceof User))  ? $data['user']  : $APPLICATION_CONTEXT->getCurrentUser();
        if($_user) {
            (strpos($text, '[user:first_name]') !== false) and $text = str_replace('[user:first_name]'       , ucfirst(mb_strtolower($_user->firstname)), $text);
        }

        return $text;
    }
}