#!/usr/bin/php
<?php

/**
 * @var Crawler $node
 * @var Crawler $talkNode
 */

require_once __DIR__ . "/vendor/autoload.php";
use \Symfony\Component\DomCrawler\Crawler;
use \Doctrine\Common\Cache\FilesystemCache;

define("CACHE_KEY","speakers");

$cache = new FilesystemCache(__DIR__.'/cache/files');

if ($cache->contains(CACHE_KEY)) {
    $speakers = $cache->fetch(CACHE_KEY);
} else {

    $client = new \Goutte\Client();

    $crawler = $client->request('GET', 'http://tek.phparch.com/speakers/');

    $speakers = $crawler->filter('#speakerlist > div')->each(function (Crawler $node) {

        $speaker = [];
        $speaker["name"] = $node->filter('div.headshot > img')->attr("alt");
        $speaker["gravatar"] = $node->filter('div.headshot > img')->attr("src");
        $speaker["company"] = $node->filter('div.info > h4')->text();
        try {
            $speaker["twitter"]  = $node->filter('div.info > h3 > a')->text();
        } catch (\Exception $e) {
            // might fail
            $speaker["twitter"]  = "";
        }

        $speaker["talks"] = $node->filter('div.info > dl')->first()->siblings()->filter('dl')->each(function (Crawler $talkNode) {

            $talk = [];
            $talk['type'] = $talkNode->filter('dt > div')->eq(0)->text();
            $talk['level'] = $talkNode->filter('dt > div')->eq(1)->text();
            $talk['title'] = $talkNode->filter('dd > h5')->text();
            $texts = [];
            foreach ($talkNode->filter('dd')->getNode(0)->childNodes as $child) {
                if ($child instanceof DOMText) {
                    $texts[] = trim($child->textContent);
                }
            }
            $talk['room'] = $texts[2];
            $talk['when'] = $texts[3];

            return $talk;
        });

        return $speaker;
    });

    $cache->save(CACHE_KEY,$speakers,60);
}

$dumper = new \Symfony\Component\Yaml\Dumper();

echo $dumper->dump(["speakers" => $speakers],999);
