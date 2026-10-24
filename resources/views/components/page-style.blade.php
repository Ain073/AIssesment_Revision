@props(['path'])

<link data-page-style href="{{ asset($path) }}?v={{ filemtime(public_path($path)) }}" rel="stylesheet">
