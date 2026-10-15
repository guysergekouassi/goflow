{{-- Entrée du menu principal (profils RH/paie et employés gestionnaires) vers les pointages hors site à valider --}}
@if(\Modules\Time\Models\PointageHorsSite::menuVisibleFor(auth()->user()))
    @php $horsSiteEnAttente = \Modules\Time\Models\PointageHorsSite::pendingCountFor(auth()->user()); @endphp
    <a href="{{ route('company.times.pointages-hors-site.index') }}"
        class="sidebar-main-item {{ Str::contains(Request::route()->getName(), 'company.times.pointages-hors-site.') ? 'active' : '' }}">
        <div class="item-icon"><i class="fas fa-map-marker-alt"></i></div>
        <span class="item-label">
            Pointages hors site
            @if($horsSiteEnAttente > 0)
                <span class="badge bg-danger rounded-pill ms-1">{{ $horsSiteEnAttente }}</span>
            @endif
        </span>
    </a>
@endif
