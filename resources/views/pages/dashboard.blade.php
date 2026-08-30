<?php

use App\Models\Profile;
use App\Profiles\CvTextExtractor;
use App\Profiles\Exceptions\UnreadableCv;
use App\Profiles\ProfileVersionWriter;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $profileName = '';

    public string $pastedText = '';

    public ?int $selectedProfileId = null;

    public $cvFile;

    public function mount(): void
    {
        $this->selectedProfileId = Profile::query()->value('id');
    }

    public function with(): array
    {
        return [
            'profiles' => Profile::query()->with('currentVersion')->latest()->get(),
            'selected' => $this->selectedProfile(),
        ];
    }

    private function selectedProfile(): ?Profile
    {
        if ($this->selectedProfileId === null) {
            return null;
        }

        // Goes through the tenant-scoped query, so an id from another
        // workspace resolves to nothing rather than to someone else's profile.
        return Profile::query()
            ->with('versions')
            ->find($this->selectedProfileId);
    }

    public function createProfile(): void
    {
        $this->validate(['profileName' => ['required', 'string', 'max:120']]);

        $profile = Profile::query()->create([
            'name' => $this->profileName,
            'is_default' => Profile::query()->count() === 0,
        ]);

        $this->profileName = '';
        $this->selectedProfileId = $profile->getKey();
    }

    public function uploadCv(CvTextExtractor $extractor, ProfileVersionWriter $writer): void
    {
        $profile = $this->selectedProfile();

        if ($profile === null) {
            $this->addError('cvFile', 'Create a profile first.');

            return;
        }

        $this->validate([
            'cvFile' => ['required', 'file', 'max:25600', 'mimes:'.implode(',', CvTextExtractor::supportedExtensions())],
        ]);

        try {
            $text = $extractor->fromFile(
                $this->cvFile->getRealPath(),
                $this->cvFile->getClientOriginalName(),
            );
        } catch (UnreadableCv $e) {
            $this->addError('cvFile', $e->getMessage());

            return;
        }

        $version = $writer->write(
            profile: $profile,
            rawText: $text,
            sourceType: 'upload',
            originalFilename: $this->cvFile->getClientOriginalName(),
        );

        $this->reset('cvFile');
        $this->dispatch('cv-saved', version: $version->version);
    }

    public function savePastedText(CvTextExtractor $extractor, ProfileVersionWriter $writer): void
    {
        $profile = $this->selectedProfile();

        if ($profile === null) {
            $this->addError('pastedText', 'Create a profile first.');

            return;
        }

        $this->validate(['pastedText' => ['required', 'string', 'min:200']]);

        try {
            $text = $extractor->fromText($this->pastedText);
        } catch (UnreadableCv $e) {
            $this->addError('pastedText', $e->getMessage());

            return;
        }

        $version = $writer->write($profile, $text, 'paste');

        $this->pastedText = '';
        $this->dispatch('cv-saved', version: $version->version);
    }
}; ?>

<div class="page">
    <header class="page-head">
        <div>
            <h1 class="page-title">Profiles</h1>
            <p class="page-sub">
                Workspace: <strong>{{ current_workspace()?->name ?? 'none' }}</strong>
            </p>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-quiet">Sign out</button>
        </form>
    </header>

    <div class="grid-2">
        <section class="card">
            <h2 class="card-title">Your profiles</h2>

            @forelse ($profiles as $profile)
                <button type="button"
                        wire:click="$set('selectedProfileId', {{ $profile->id }})"
                        class="profile-row @if($profile->id === $selectedProfileId) is-active @endif">
                    <span class="profile-name">{{ $profile->name }}</span>
                    <span class="profile-meta">
                        @if ($profile->currentVersion)
                            v{{ $profile->currentVersion->version }}
                        @else
                            no CV yet
                        @endif
                    </span>
                </button>
            @empty
                <p class="empty">No profiles yet. Create one to upload a CV against.</p>
            @endforelse

            <form wire:submit="createProfile" class="inline-form">
                <input type="text" wire:model="profileName" placeholder="Profile name" maxlength="120">
                <button type="submit" class="btn btn-primary">Add</button>
            </form>
            @error('profileName') <span class="field-error">{{ $message }}</span> @enderror
        </section>

        <section class="card">
            <h2 class="card-title">
                CV
                @if ($selected)
                    <span class="card-title-sub">for {{ $selected->name }}</span>
                @endif
            </h2>

            @if (! $selected)
                <p class="empty">Select or create a profile first.</p>
            @else
                <form wire:submit="uploadCv" class="stack">
                    <label class="field">
                        <span class="field-label">Upload — PDF, TXT or Markdown, max 25 MB</span>
                        <input type="file" wire:model="cvFile" accept=".pdf,.txt,.md">
                        @error('cvFile') <span class="field-error">{{ $message }}</span> @enderror
                    </label>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="cvFile,uploadCv">Save version</span>
                        <span wire:loading wire:target="cvFile,uploadCv">Reading…</span>
                    </button>
                </form>

                <p class="or">or paste the text</p>

                <form wire:submit="savePastedText" class="stack">
                    <textarea wire:model="pastedText" rows="6"
                              placeholder="Paste your CV text here…"></textarea>
                    @error('pastedText') <span class="field-error">{{ $message }}</span> @enderror
                    <button type="submit" class="btn">Save version</button>
                </form>

                @if ($selected->versions->isNotEmpty())
                    <h3 class="card-subtitle">Version history</h3>
                    <ul class="versions">
                        @foreach ($selected->versions as $version)
                            <li>
                                <span class="v-num">v{{ $version->version }}</span>
                                <span class="v-meta">
                                    {{ $version->original_filename ?? 'pasted' }}
                                    · {{ $version->created_at->diffForHumans() }}
                                    · {{ number_format(strlen($version->raw_text)) }} chars
                                </span>
                                @if ($version->id === $selected->current_version_id)
                                    <span class="v-current">current</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <p class="hint">
                        Versions are immutable. Re-uploading an unchanged CV reuses the
                        existing version rather than re-paying to score against a new one.
                    </p>
                @endif
            @endif
        </section>
    </div>
</div>
