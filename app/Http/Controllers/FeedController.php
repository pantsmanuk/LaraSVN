<?php

namespace App\Http\Controllers;

use App\Http\RepositoryRequest;
use App\Svn\SvnClient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use XMLWriter;

final class FeedController extends Controller
{
    public function __invoke(Request $request, SvnClient $svn): Response
    {
        $input = RepositoryRequest::parse($request, [
            'max' => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'quiet' => ['sometimes', 'string', 'max:20'],
            'isdir' => ['sometimes', 'in:0,1'],
        ]);
        $repository = $input['repname'];
        $svn->base($repository);
        abort_unless(($svn->repositories()[$repository]['rss'] ?? true), 403, 'Revision feeds are disabled for this repository.');
        $info = $svn->info($repository, $input['path'], $input['rev'], $input['peg']);
        $quiet = array_key_exists('quiet', $input);
        $entries = $info['revision'] === 0 ? [] : $svn->history($repository, $input['path'], $info['revision'], $info['peg'], (int) ($input['max'] ?? 0) ?: 40, 1, ! $quiet);
        $params = ['repname' => $repository, 'path' => $input['path'], 'rev' => $info['revision'], 'peg' => $info['peg']];
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:dc', 'http://purl.org/dc/elements/1.1/');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->startElement('channel');
        $xml->writeElement('title', $repository.' '.$input['path'].' revisions');
        $xml->writeElement('link', route('log', $params));
        $xml->writeElement('description', 'Subversion revision history');
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', route('rss', array_intersect_key($input, array_flip(['repname', 'path', 'rev', 'peg', 'max', 'quiet']))));
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();
        foreach ($entries as $entry) {
            $url = route('revision', ['repname' => $repository, 'rev' => $entry['revision']]);
            $xml->startElement('item');
            $xml->writeElement('title', 'r'.$entry['revision'].' '.$entry['message']);
            $xml->writeElement('link', $url);
            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', 'true');
            $xml->text($url);
            $xml->endElement();
            $xml->writeElement('dc:creator', $entry['author']);
            if ($entry['date'] !== '') {
                $xml->writeElement('pubDate', gmdate(DATE_RSS, strtotime($entry['date'])));
            }
            $description = '<p>'.nl2br(e($entry['message'])).'</p>';
            if (! $quiet && $entry['changes'] !== []) {
                $description .= '<ul>';
                foreach ($entry['changes'] as $change) {
                    $description .= '<li>'.e($change['action'].' '.$change['path']);
                    if ($change['copyFrom'] !== '') {
                        $description .= ' copied from '.e($change['copyFrom']).' at r'.$change['copyRevision'];
                    }
                    if ($change['propertiesModified']) {
                        $description .= ' (properties changed)';
                    }
                    $description .= '</li>';
                }
                $description .= '</ul>';
            }
            $xml->writeElement('description', $description);
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
