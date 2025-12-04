@extends('layouts.home')
@section('content')

<div class="scroll-container news-wrapper">
    <div class="scroll-panel news-container">
        <h1>
            {!! $translation["News"] !!}
        </h1>
        @php
            $allAnnouncements = $announcementService->getAllNews();
        @endphp
        @foreach($allAnnouncements as $announcement)
            <div class="announcement-card">
                <h2 class="announcement-title">{{ $announcement->title }}</h2>
                <h3 class="announcement-date">{{ $announcement->created_at->format('F j, Y') }}</h3>
                <div class="announcement-content">
                    {!! \Illuminate\Mail\Markdown::parse($announcementService->renderAnnouncement($announcement)) !!}
                </div>
            </div>
        @endforeach
        <div class="news-pagination">
            @if($allAnnouncements->currentPage() > 1)
                <a href="{{ $allAnnouncements->previousPageUrl() }}">
                    <button class="btn btn-md btn-md-stroke">
                        {!! $translation["News_PrevPage"] !!}
                    </button>
                </a>
            @endif
            @if($allAnnouncements->hasMorePages() && $allAnnouncements->currentPage() < $allAnnouncements->lastPage())
                <a href="{{ $allAnnouncements->nextPageUrl() }}">
                    <button class="btn btn-md btn-md-stroke">
                        {!! $translation["News_NextPage"] !!}
                    </button>
                </a>
            @endif
        </div>
    </div>
</div>

@endsection
