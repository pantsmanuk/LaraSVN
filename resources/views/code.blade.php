@extends('layout')
@section('title', basename($path).' · '.ucfirst($mode).' · LaraSVN')
@section('content')
@php
    $documents = ['new' => $document->text];
    if ($old) $documents['old'] = $old->text;
@endphp
@include('partials.context')
<div class="page-heading"><div><p class="eyebrow">{{ $mode === 'diff' ? 'Changes' : ($mode === 'blame' ? 'Line history' : 'Source') }}</p><h1>{{ basename($path) }}</h1></div><span class="revision-badge">r{{ $revision }}</span></div>
@include('partials.navigation')
<section class="commit" aria-label="Last change">
    <a class="commit-revision" href="{{ route('revision', ['repname' => $repository, 'path' => '/', 'rev' => $history[0]['revision']]) }}">r{{ $history[0]['revision'] }}</a><span class="commit-message">{{ $history[0]['message'] }}</span><span class="commit-author">{{ $history[0]['author'] }}</span>
</section>
@if($mode === 'diff')
    <div class="code-toolbar">
        <span>{{ $previous ? 'r'.$previous.' → r'.$revision : 'First revision' }}</span>
        <div class="toolbar-actions">
            <a href="{{ route('diff', $params + ['all' => $all ? '0' : '1', 'ignorews' => $ignoreWhitespace ? '1' : '0']) }}">{{ $all ? 'Compact view' : 'Entire file' }}</a>
            <a href="{{ route('diff', $params + ['all' => $all ? '1' : '0', 'ignorews' => $ignoreWhitespace ? '0' : '1']) }}">{{ $ignoreWhitespace ? 'Regard whitespace' : 'Ignore whitespace' }}</a>
        </div>
    </div>
    @if(!$previous)
        <p class="notice">No previous change exists for this file. <a href="{{ route('source', $params) }}">View source</a>.</p>
    @elseif($rows === [])
        <p class="notice">No text differences with these comparison settings.</p>
    @else
        <div class="code-scroll prism-surface" tabindex="0" role="region" aria-label="Source comparison">
            <div class="diff-table" data-language="{{ $language }}">
                <div class="diff-heading"><span>Previous · r{{ $previous }}</span><span>Current · r{{ $revision }}</span></div>
                @foreach($rows as $row)
                    @if($row['kind'] === 'hunk')
                        <div class="hunk">{{ $row['label'] }}</div>
                    @else
                        <div class="diff-row {{ $row['kind'] }}">
                            @foreach(['old' => ['oldLine', 'oldText'], 'new' => ['newLine', 'newText']] as $side => [$lineKey, $textKey])
                                <div class="diff-side {{ $side }} {{ $row[$lineKey] === null ? 'absent' : '' }}">
                                    @if($row[$lineKey] !== null)
                                        <a class="gutter" id="{{ $side }}-{{ $row[$lineKey] }}" href="#{{ $side }}-{{ $row[$lineKey] }}" aria-label="{{ $side === 'old' ? 'Previous' : 'Current' }} line {{ $row[$lineKey] }}">{{ $row[$lineKey] }}</a><span class="change-marker" aria-hidden="true">{{ $row['kind'] === 'change' ? ($side === 'old' ? '−' : '+') : ' ' }}</span><code data-document="{{ $side }}" data-line="{{ $row[$lineKey] }}">{{ $row[$textKey] }}</code>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
        <div class="source-meta">Previous: {{ $old->finalNewline ? 'final newline' : 'no final newline' }} · Current: {{ $document->finalNewline ? 'final newline' : 'no final newline' }}</div>
    @endif
@else
    <div class="code-toolbar"><span>{{ count($document->lines) }} lines</span><button type="button" data-copy="new" hidden>Copy source</button><span id="copy-status" role="status"></span></div>
    <div class="code-scroll prism-surface" tabindex="0" role="region" aria-label="{{ $mode === 'blame' ? 'Source with line attribution' : 'Source code' }}">
        <div class="source-table {{ $mode }}" data-language="{{ $language }}">
            @forelse($document->lines as $index => $line)
                <div class="source-row">
                    @if($mode === 'blame')
                        <span class="attribution">
                            @if($attribution[$index + 1]['revision'] !== null)
                                <a href="{{ route('blame', array_replace($params, ['rev' => $attribution[$index + 1]['revision']])) }}">r{{ $attribution[$index + 1]['revision'] }}</a><span>{{ $attribution[$index + 1]['author'] }}</span>
                            @else
                                <span>No attribution</span>
                            @endif
                        </span>
                    @endif
                    <a class="gutter" id="l{{ $index + 1 }}" href="#l{{ $index + 1 }}" aria-label="Line {{ $index + 1 }}">{{ $index + 1 }}</a><code data-document="new" data-line="{{ $index + 1 }}">{{ $line }}</code>
                </div>
            @empty
                <p class="notice">This file is empty.</p>
            @endforelse
        </div>
    </div>
    <div class="source-meta">UTF-8 · {{ str_contains($document->bytes, "\r\n") ? 'CRLF' : 'LF' }} · {{ $document->finalNewline ? 'Final newline' : 'No final newline' }}</div>
@endif
@include('partials.properties')
<p class="enhancement-status" role="status" id="highlight-status"></p>
<script type="application/json" id="source-documents">@json($documents)</script>
@endsection
