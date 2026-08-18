@extends('landingpage::master.app')

@section('title', 'Inscription - GPFlow')

@section('content')
<section class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="text-center mb-8">
                <a href="{{ route('landingpage') }}" class="inline-flex items-center mb-6">
                   <img src="{{ asset('img/logos/logo-dark.png') }}" width="100px" alt="logo">
                </a>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Créer votre compte GPFlow</h1>
                <p class="text-gray-600">Rejoignez des centaines d'entreprises qui font confiance à GPFlow pour la gestion de leur paie</p>
            </div>

            <!-- Formulaire d'inscription -->
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <!-- Messages de succès/erreur -->
                @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ti ti-check me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif

                @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ti ti-alert-triangle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                @endif
                <form action="{{ route('register.store') }}" method="POST" class="space-y-6" id="registrationForm">
                    @csrf

                    <!-- Alertes de validation globales -->
                    @if ($errors->any())
                        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6" id="error-summary">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="ri-error-warning-fill text-red-400 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700 font-bold">
                                        Il y a des erreurs dans le formulaire. Veuillez vérifier les champs en rouge.
                                    </p>
                                    <ul class="mt-1 text-xs text-red-600 list-disc list-inside">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    @endif
                    
                    <!-- Abonnement sélectionné : forfait + employés supplémentaires -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6"
                        data-base-price="{{ (float) $plan->price }}"
                        data-included-employees="{{ (int) $plan->included_employees }}"
                        data-price-per-employee="{{ (float) $plan->price_per_employee }}"
                        id="subscription-summary">
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div>
                                <h3 class="font-semibold text-blue-900">Abonnement sélectionné</h3>
                                <p class="text-blue-700">{{ $plan->name }}</p>
                                @if($plan->price_per_employee > 0)
                                    <p class="text-sm text-blue-600 mt-1">
                                        Forfait {{ number_format($plan->price, 0, ',', ' ') }} FCFA
                                        ({{ $plan->included_employees }} employés inclus)
                                        + {{ number_format($plan->price_per_employee, 0, ',', ' ') }} FCFA
                                        par employé supplémentaire
                                    </p>
                                @endif
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-blue-900" id="summary-total">
                                    {{ number_format($plan->price, 0, ',', ' ') }} FCFA
                                </p>
                                <p class="text-sm text-blue-600">par mois</p>
                            </div>
                        </div>
                    </div>

                    <!-- Messages d'erreur globaux -->
                    @if ($errors->has('plan_id') || $errors->has('terms'))
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="ri-error-warning-line text-red-400 text-xl"></i>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Erreur de validation</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc list-inside space-y-1">
                                            @if ($errors->has('plan_id'))
                                                <li>{{ $errors->first('plan_id') }}</li>
                                            @endif
                                            @if ($errors->has('terms'))
                                                <li>{{ $errors->first('terms') }}</li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Informations personnelles -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Informations personnelles</h3>
                            
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Nom complet <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="name" name="name" required
                                    class="w-full px-4 py-2 border {{ $errors->has('name') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    value="{{ old('name') }}" placeholder="Jean Dupont" oninput="generateProposal()">
                                @if ($errors->has('name'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('name') }}</p>
                                @endif
                            </div>

                            <input type="text"
                                id="username"
                                name="username"
                                value="{{ old('username') }}"
                                style="display: none;">
                                
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Adresse email <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" required
                                    class="w-full px-4 py-2 border {{ $errors->has('email') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    value="{{ old('email') }}" placeholder="jean@exemple.com">
                                @if ($errors->has('email'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('email') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    Téléphone <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" id="phone" name="phone" required
                                    class="w-full px-4 py-2 border {{ $errors->has('phone') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    value="{{ old('phone') }}" placeholder="+225 07 67 13 19 93">
                                @if ($errors->has('phone'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('phone') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Mot de passe <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="password" name="password" required
                                    class="w-full px-4 py-2 border {{ $errors->has('password') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    placeholder="••••••••">
                                <p class="text-xs text-gray-500 mt-1">Minimum 8 caractères</p>
                                @if ($errors->has('password'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('password') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                                    Confirmer le mot de passe <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="password_confirmation" name="password_confirmation" required
                                    class="w-full px-4 py-2 border {{ $errors->has('password_confirmation') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    placeholder="••••••••">
                                @if ($errors->has('password_confirmation'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('password_confirmation') }}</p>
                                @endif
                            </div>
                        </div>

                        <!-- Informations de l'entreprise -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-900 border-b pb-2">Informations de l'entreprise</h3>
                            
                            <div>
                                <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Nom de l'entreprise <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="company_name" name="company_name" required
                                    class="w-full px-4 py-2 border {{ $errors->has('company_name') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    value="{{ old('company_name') }}" placeholder="SARL Exemple">
                                @if ($errors->has('company_name'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('company_name') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="company_address" class="block text-sm font-medium text-gray-700 mb-1">
                                    Adresse
                                </label>
                                <textarea id="company_address" name="company_address" rows="3"
                                    class="w-full px-4 py-2 border {{ $errors->has('company_address') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    placeholder="Abidjan, Cocody, Rue des Princes">{{ old('company_address') }}</textarea>
                                @if ($errors->has('company_address'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('company_address') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="company_phone" class="block text-sm font-medium text-gray-700 mb-1">
                                    Téléphone de l'entreprise
                                </label>
                                <input type="tel" id="company_phone" name="company_phone"
                                    class="w-full px-4 py-2 border {{ $errors->has('company_phone') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    value="{{ old('company_phone') }}" placeholder="+225 27 20 00 00 00">
                                @if ($errors->has('company_phone'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('company_phone') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="company_email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email de l'entreprise
                                </label>
                                <input type="email" id="company_email" name="company_email"
                                    class="w-full px-4 py-2 border {{ $errors->has('company_email') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    value="{{ old('company_email') }}" placeholder="contact@entreprise.com">
                                @if ($errors->has('company_email'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('company_email') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="company_size" class="block text-sm font-medium text-gray-700 mb-1">
                                    Taille de l'entreprise <span class="text-red-500">*</span>
                                </label>
                                <select id="company_size" name="company_size" required
                                    class="w-full px-4 py-2 border {{ $errors->has('company_size') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent">
                                    <option value="">Sélectionner...</option>
                                    <option value="startup" {{ old('company_size') == 'startup' ? 'selected' : '' }}>Startup</option>
                                    <option value="small" {{ old('company_size') == 'small' ? 'selected' : '' }}>Petite entreprise (1-50 employés)</option>
                                    <option value="medium" {{ old('company_size') == 'medium' ? 'selected' : '' }}>Moyenne entreprise (51-200 employés)</option>
                                    <option value="large" {{ old('company_size') == 'large' ? 'selected' : '' }}>Grande entreprise (201-1000 employés)</option>
                                    <option value="enterprise" {{ old('company_size') == 'enterprise' ? 'selected' : '' }}>Grande entreprise (1000+ employés)</option>
                                </select>
                                @if ($errors->has('company_size'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('company_size') }}</p>
                                @endif
                            </div>

                            <div>
                                <label for="employees_count" class="block text-sm font-medium text-gray-700 mb-1">
                                    Nombre d'employés à gérer <span class="text-red-500">*</span>
                                </label>
                                <input type="number" id="employees_count" name="employees_count" required min="1" max="10000"
                                    class="w-full px-4 py-2 border {{ $errors->has('employees_count') ? 'border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-blue-500' }} rounded-lg focus:ring-2 focus:border-transparent"
                                    value="{{ old('employees_count', $employeesCount) }}">
                                @if($plan->price_per_employee > 0)
                                    <p class="text-xs text-gray-500 mt-1">
                                        Ce nombre détermine le montant de votre abonnement.
                                        Les {{ $plan->included_employees }} premiers employés sont inclus dans le forfait.
                                    </p>
                                @endif
                                @if ($errors->has('employees_count'))
                                    <p class="mt-1 text-sm text-red-600">{{ $errors->first('employees_count') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Détail du montant recalculé en direct -->
                    @if($plan->price_per_employee > 0)
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <h3 class="font-semibold text-gray-900 mb-3">
                                <i class="ri-calculator-line mr-2"></i>Détail de votre abonnement
                            </h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Forfait mensuel</span>
                                    <span class="font-medium">{{ number_format($plan->price, 0, ',', ' ') }} FCFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">
                                        <span id="summary-extra-count">0</span> employé(s) au-delà de
                                        {{ $plan->included_employees }}
                                        × {{ number_format($plan->price_per_employee, 0, ',', ' ') }} FCFA
                                    </span>
                                    <span class="font-medium" id="summary-extra-amount">0 FCFA</span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-gray-200">
                                    <span class="font-bold text-gray-900">Total à payer par mois</span>
                                    <span class="font-bold text-blue-600" id="summary-total-detail">
                                        {{ number_format($plan->price, 0, ',', ' ') }} FCFA
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Plan sélectionné (champ caché) -->
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">

                    <!-- Conditions générales -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <label class="flex items-start">
                            <input type="checkbox" name="terms" value="1" required
                                class="mt-1 mr-3 h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 {{ $errors->has('terms') ? 'border-red-500 focus:ring-red-500' : '' }}"
                                {{ old('terms') ? 'checked' : '' }}>
                            <span class="text-sm text-gray-600">
                                J'accepte les <a href="#" class="text-blue-600 hover:underline">conditions générales d'utilisation</a> 
                                et la <a href="#" class="text-blue-600 hover:underline">politique de confidentialité</a> de GPFlow.
                                Je comprends que mes données seront traitées conformément à la réglementation en vigueur.
                            </span>
                        </label>
                        @if ($errors->has('terms'))
                            <p class="mt-2 text-sm text-red-600 ml-7">{{ $errors->first('terms') }}</p>
                        @endif
                    </div>

                    <!-- Boutons d'action -->
                    <div class="flex items-center justify-between pt-6 border-t">
                        <a href="{{ route('landingpage') }}" class="text-gray-600 hover:text-gray-800 font-medium">
                            <i class="ri-arrow-left-line mr-2"></i>Retour à l'accueil
                        </a>
                        <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            <i class="ri-user-add-line mr-2"></i>Créer mon compte
                        </button>
                    </div>
                </form>
            </div>

            <!-- Informations de paiement -->
            <div class="mt-8 bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-yellow-900 mb-3">
                    <i class="ri-bank-card-line mr-2"></i>Informations de paiement
                </h3>
                <div class="space-y-3 text-sm text-yellow-800">
                    <p><strong>Paiement sécurisé par Genius Pay</strong></p>
                    <p>Après validation de votre inscription, vous serez redirigé vers la page de paiement :</p>
                    <div class="bg-white rounded-lg p-4 space-y-2">
                        <p><strong>Moyens acceptés :</strong> Orange Money, MTN Money, Moov Money, Wave, Visa/Mastercard</p>
                        <p class="text-xs text-gray-600">Veuillez conserver votre reçu de paiement pour toute vérification</p>
                    </div>
                    <p class="text-xs">Une fois le paiement confirmé, votre compte sera activé immédiatement.</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
// Définir la fonction en premier pour qu'elle soit disponible globalement
function generateProposal() {
    var nameField = document.getElementById('name');
    var usernameField = document.getElementById('username');
    var username = nameField.value.replace(/\s+/g, '').toUpperCase(); // Supprime tous les espaces et convertit en majuscules

    if (username.length >= 4) {
        var firstFourLetters = username.substring(0, 4); // Prend les quatre premières lettres
        var randomDigits = Math.floor(10 + Math.random() * 90); // Génère deux chiffres aléatoires
        var proposal = firstFourLetters + randomDigits; // Combine les lettres et les chiffres

        usernameField.value = proposal; // Met à jour la valeur du champ avec la proposition générée
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const password = document.getElementById('password');
    const passwordConfirmation = document.getElementById('password_confirmation');

    function validatePasswords() {
        if (password.value !== passwordConfirmation.value) {
            passwordConfirmation.setCustomValidity('Les mots de passe ne correspondent pas');
        } else {
            passwordConfirmation.setCustomValidity('');
        }
    }
    
    password.addEventListener('change', validatePasswords);
    passwordConfirmation.addEventListener('keyup', validatePasswords);

    // Recalcul du montant : forfait + employés au-delà de ceux inclus
    const summary = document.getElementById('subscription-summary');
    const employeesInput = document.getElementById('employees_count');

    if (summary && employeesInput) {
        const basePrice = parseFloat(summary.dataset.basePrice || '0');
        const included = parseInt(summary.dataset.includedEmployees || '0', 10);
        const perEmployee = parseFloat(summary.dataset.pricePerEmployee || '0');

        const format = function (amount) {
            // toLocaleString sépare par des espaces insécables : on les normalise
            return Math.round(amount).toLocaleString('fr-FR').replace(/[  ]/g, ' ') + ' FCFA';
        };

        const refreshSummary = function () {
            const count = Math.min(10000, Math.max(1, parseInt(employeesInput.value, 10) || 1));
            const billable = Math.max(0, count - included);
            const employeesAmount = billable * perEmployee;

            const set = function (id, value) {
                const el = document.getElementById(id);
                if (el) el.textContent = value;
            };

            set('summary-extra-count', billable);
            set('summary-extra-amount', format(employeesAmount));
            set('summary-total', format(basePrice + employeesAmount));
            set('summary-total-detail', format(basePrice + employeesAmount));
        };

        employeesInput.addEventListener('input', refreshSummary);
        refreshSummary();
    }
    
    // Mettre le focus sur le premier champ en erreur
    const firstErrorField = document.querySelector('input.border-red-500, textarea.border-red-500, select.border-red-500');
    if (firstErrorField) {
        firstErrorField.focus();
        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>
@endpush