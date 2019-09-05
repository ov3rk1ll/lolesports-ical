<?php

use Eluceo\iCal\Component\Event;
use Eluceo\iCal\Component\Calendar;
use GuzzleHttp\Exception\ClientException;

require 'vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

// This is stolen from the watch.lolesports.com site but I couldn't find anywhere to ask for an API key
$apiKey = '0TvQnueqKa5mxJntVWt0w4LpLfEkrV1Ta8rQBb9Z';

// So they know whom to complain to if the 48 requests per day kill their server xD
$userAgent = 'ICal Generator/1.0 (overkillerror@gmail.com)';

$client = new GuzzleHttp\Client();

$language = 'en-GB';
$leagues = [];

// Get the list of leagues
try {
    $res = $client->request('GET', 'https://esports-api.lolesports.com/persisted/gw/getLeagues?hl=' . $language, [
        'headers' => [
            'User-Agent' => $userAgent,
            'Accept'     => 'application/json',
            'x-api-key'  => $apiKey,
        ]
    ]);
} catch (ClientException $e) {
    if ($e->getResponse()->getStatusCode() !== 200) {
        echo 'Error in getLeagues: Unexpected status code ' . $e->getResponse()->getStatusCode() . "\n";
        return;
    }
}

// Parse the json data
$body = $res->getBody();
$json = json_decode($body, true);
$_leagues = $json['data']['leagues'];

// Create the array of leagues we want to look at
foreach ($_leagues as $league) {
    $leagues[] = [
        'id' => $league['id'],
        'name' => $league['name'],
        'slug' => $league['slug'],
        'image' => $league['image'],
        'region' => $league['region'],
        'priority' => $league['priority'],
    ];
}

// Sort leagues by priority to display the same list as on watch.lolesports.com
usort($leagues, function ($a, $b) {
    $x = $a['priority'];
    $y = $b['priority'];
    if ($x == $y)
        return 0;
    else if ($x < $y)
        return -1;
    else
        return 1;
});

// Loop through all leages
foreach ($leagues as $league) {
    // Get the schedule for the leage
    try {
        $res = $client->request('GET', 'https://esports-api.lolesports.com/persisted/gw/getSchedule?hl=' . $language . '&leagueId=' . $league['id'], [
            'headers' => [
                'User-Agent' => $userAgent,
                'Accept'     => 'application/json',
                'x-api-key'  => $apiKey,
            ]
        ]);
    } catch (ClientException $e) {
        if ($e->getResponse()->getStatusCode() !== 200) {
            echo 'Error in getSchedule: Unexpected status code ' . $e->getResponse()->getStatusCode() . "\n";
            return;
        }
    }

    // Parse the json data
    $body = $res->getBody();
    $json = json_decode($body, true);
    $events = $json['data']['schedule']['events'];

    // Create the Calendar object with the URL and name
    $vCalendar = new Calendar('https://watch.lolesports.com/schedule?leagues=' . $league['slug']);
    $vCalendar->setName("LoL Esports " . $league['name']);

    $count = 0;
    // Lopp through all events in that league
    foreach ($events as $event) {
        if ($event['type'] !== 'match') {
            continue; // skip if not a match
        }

        // Get the match data and create the event object
        $match = $event['match'];
        $vEvent = new Event();
        $vEvent->setDtStart(new \DateTime($event['startTime']));
        // We don't know the end time so we just use the start time again
        $vEvent->setDtEnd(new \DateTime($event['startTime']));
        // Event title is the two teams, the league and the block (eg "Week 8")
        $vEvent->setSummary($match['teams'][0]['code'] . ' vs ' . $match['teams'][1]['code'] . ' - ' . $event['league']['name'] . ' ' . $event['blockName']);
        // Use the match id as UID
        $vEvent->setUniqueId($match['id']);

        $vCalendar->addComponent($vEvent);
        $count++;
    }

    // Save the ics file
    file_put_contents('ics/' . $league['slug'] . '.ics', $vCalendar->render());

    // Log message for this league
    echo 'Created ' . $league['slug'] . '.ics' . ' for league ' . $league['name'] . ' with ' . $count . ' events' . "\n";
}

// Store list of leagues as json for the index page to load
file_put_contents('ics/leagues.json', json_encode($leagues));
