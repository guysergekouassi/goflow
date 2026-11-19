@extends('layouts.auth')

@section('title', 'Choisissez votre mot de passe - GP Flow')
@section('subtitle', 'Choisissez votre mot de passe')

@section('content')
<div class="alert alert-info small">
    <i class="ti ti-shield-lock me-1"></i>
    Vous vous êtes connecté avec un <strong>mot de passe provisoire</strong>.
    Pour protéger votre compte, choisissez maintenant votre propre mot de passe.
</div>

<form method="POST" action="{{ route('mot-de-passe.provisoire.enregistrer') }}" novalidate>
    @csrf

    <div class="mb-3">
        <label for="mot_de_passe_actuel" class="form-label">Mot de passe provisoire reçu</label>
        <input type="password" class="form-control @error('mot_de_passe_actuel') is-invalid @enderror"
               id="mot_de_passe_actuel" name="mot_de_passe_actuel" required autocomplete="current-password">
        @error('mot_de_passe_actuel')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="nouveau_mot_de_passe" class="form-label">Nouveau mot de passe</label>
        <input type="password" class="form-control @error('nouveau_mot_de_passe') is-invalid @enderror"
               id="nouveau_mot_de_passe" name="nouveau_mot_de_passe" required minlength="8" autocomplete="new-password">
        @error('nouveau_mot_de_passe')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">8 caractères minimum.</div>
    </div>

    <div class="mb-4">
        <label for="nouveau_mot_de_passe_confirmation" class="form-label">Confirmez le nouveau mot de passe</label>
        <input type="password" class="form-control" id="nouveau_mot_de_passe_confirmation"
               name="nouveau_mot_de_passe_confirmation" required minlength="8" autocomplete="new-password">
    </div>

    <div class="d-grid mb-3">
        <button type="submit" class="btn btn-primary btn-lg text-white">
            <i class="ti ti-check me-2"></i>
            Enregistrer mon mot de passe
        </button>
    </div>
</form>

<form method="POST" action="{{ route('logout') }}" class="text-center">
    @csrf
    <button type="submit" class="btn btn-link text-decoration-none">Se déconnecter</button>
</form>
@endsection
