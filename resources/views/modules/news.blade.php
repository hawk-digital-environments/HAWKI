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
                <div class="announcement-header">
                    <h3 class="announcement-date">{{ ($announcement->starts_at ?? $announcement->created_at)->format('F j, Y') }}</h3>
                </div>
                <div class="announcement-content">
                    @php
                        $content = \Illuminate\Mail\Markdown::parse($announcementService->renderAnnouncement($announcement));
                        // Downgrade headings: h1->h2, h2->h3, h3->h4, h4->h5, h5->h6
                        $content = preg_replace('/<\/h5>/i', '</h6>', $content);
                        $content = preg_replace('/<h5>/i', '<h6>', $content);
                        $content = preg_replace('/<\/h4>/i', '</h5>', $content);
                        $content = preg_replace('/<h4>/i', '<h5>', $content);
                        $content = preg_replace('/<\/h3>/i', '</h4>', $content);
                        $content = preg_replace('/<h3>/i', '<h4>', $content);
                        $content = preg_replace('/<\/h2>/i', '</h3>', $content);
                        $content = preg_replace('/<h2>/i', '<h3>', $content);
                        $content = preg_replace('/<\/h1>/i', '</h2>', $content);
                        $content = preg_replace('/<h1>/i', '<h2>', $content);
                    @endphp
                    {!! $content !!}
                </div>
                <div class="announcement-footer">
                    <div class="announcement-audience">
                        @if($announcement->is_global)
                            <span class="announcement-target">{!! $translation["News_PublishedGlobally"] !!}</span>
                        @elseif(!empty($announcement->target_roles))
                            @php
                                $roles = collect($announcement->target_roles)->map(function($slug) {
                                    $role = \Orchid\Platform\Models\Role::where('slug', $slug)->first();
                                    return $role ? $role->name : ucfirst($slug);
                                })->join(', ');
                            @endphp
                            <span class="announcement-target">{!! $translation["News_PublishedIn"] !!} {{ $roles }}</span>
                        @else
                            <span class="announcement-target">{!! $translation["News_PublishedGlobally"] !!}</span>
                        @endif
                    </div>
                    @php
                        $readCount = $announcement->users()->wherePivotNotNull('seen_at')->count();
                    @endphp
                    <span class="announcement-read-count">{!! $translation["News_ReadBy"] !!} {{ $readCount }}</span>
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
