@php
    $assessment = $siteVisit->assessment_data ?? [];
    $selectedTools = old('tools', $assessment['tools'] ?? []);
    $selectedPpe = old('ppe', $assessment['ppe'] ?? []);
@endphp
<section class="admin-card site-assessment-section">
    <div class="card-head">
        <div><p>Physical site visit</p><h2>Site assessment checklist</h2></div>
        @if($siteVisit->assessment_completed_at)
            <span class="assessment-complete">Completed</span>
        @else
            <span class="assessment-draft">Draft</span>
        @endif
    </div>
    <form method="post" action="{{ route('admin.site-visits.assessment.update', $siteVisit) }}" class="site-assessment-form">
        @csrf @method('patch')
        <fieldset class="assessment-step">
            <legend><span>01</span> Site profile</legend>
            <div class="assessment-grid three">
                <label><span>How did they find CuciNow.co?</span><select name="discovery_source"><option value="">Select source</option>@foreach(config('site_visits.discovery_sources') as $value => $label)<option value="{{ $value }}" @selected(old('discovery_source', $assessment['discovery_source'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label><span>Estimated area (sq ft)</span><input type="number" name="site_size_sqft" min="1" max="100000000" value="{{ old('site_size_sqft', $assessment['site_size_sqft'] ?? '') }}" placeholder="e.g. 12500"></label>
                <label><span>Number of floors</span><input type="number" name="floor_count" min="1" max="500" value="{{ old('floor_count', $assessment['floor_count'] ?? '') }}" placeholder="e.g. 3"></label>
            </div>
            <div class="assessment-grid two">
                <label><span>Other discovery source</span><input name="discovery_source_other" maxlength="180" value="{{ old('discovery_source_other', $assessment['discovery_source_other'] ?? '') }}" placeholder="Complete only when Other is selected"></label>
                <label><span>Premises type</span><select name="premises_type"><option value="">Select premises</option>@foreach(config('site_visits.premises_types') as $value => $label)<option value="{{ $value }}" @selected(old('premises_type', $assessment['premises_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label class="span-two"><span>Other premises type</span><input name="premises_type_other" maxlength="180" value="{{ old('premises_type_other', $assessment['premises_type_other'] ?? '') }}" placeholder="Complete only when Other is selected"></label>
            </div>
        </fieldset>

        <fieldset class="assessment-step">
            <legend><span>02</span> Equipment &amp; safety</legend>
            <div class="assessment-choice-columns">
                <div><strong>Machinery / tools to bring</strong><div class="assessment-checks">@foreach(config('site_visits.tools') as $value => $label)<label><input type="checkbox" name="tools[]" value="{{ $value }}" @checked(in_array($value, $selectedTools, true))><span>{{ $label }}</span></label>@endforeach</div></div>
                <div><strong>PPE required</strong><div class="assessment-checks">@foreach(config('site_visits.ppe') as $value => $label)<label><input type="checkbox" name="ppe[]" value="{{ $value }}" @checked(in_array($value, $selectedPpe, true))><span>{{ $label }}</span></label>@endforeach</div></div>
            </div>
            <div class="assessment-grid two">
                <label><span>Other tools / machinery</span><textarea name="other_tools" rows="3" maxlength="1000" placeholder="Special attachments, chemicals, generators, access equipment…">{{ old('other_tools', $assessment['other_tools'] ?? '') }}</textarea></label>
                <label><span>Other PPE requirements</span><textarea name="other_ppe" rows="3" maxlength="1000" placeholder="Customer-specific safety requirements…">{{ old('other_ppe', $assessment['other_ppe'] ?? '') }}</textarea></label>
            </div>
            <div class="assessment-yes-no">
                <label><input type="hidden" name="safety_signage_required" value="0"><input type="checkbox" name="safety_signage_required" value="1" @checked(old('safety_signage_required', $assessment['safety_signage_required'] ?? false))><span>Safety signage / barricades required</span></label>
                <label><input type="hidden" name="forklift_required" value="0"><input type="checkbox" name="forklift_required" value="1" @checked(old('forklift_required', $assessment['forklift_required'] ?? false))><span>Forklift required</span></label>
            </div>
        </fieldset>

        <fieldset class="assessment-step">
            <legend><span>03</span> Time &amp; access</legend>
            <div class="assessment-grid three">
                <label><span>Expected cleaning duration (hours)</span><input type="number" name="expected_duration_hours" min="0.25" max="10000" step="0.25" value="{{ old('expected_duration_hours', $assessment['expected_duration_hours'] ?? '') }}" placeholder="e.g. 8"></label>
                <label class="span-two"><span>Access, loading and site restrictions</span><textarea name="access_notes" rows="3" maxlength="2000" placeholder="Loading bay, lift access, work permits, operating hours…">{{ old('access_notes', $assessment['access_notes'] ?? '') }}</textarea></label>
                <label class="span-three"><span>Assessment notes / recommended cleaning approach</span><textarea name="assessment_notes" rows="4" maxlength="3000">{{ old('assessment_notes', $assessment['assessment_notes'] ?? '') }}</textarea></label>
            </div>
        </fieldset>

        @if($siteVisit->assessment_completed_at)
            <p class="assessment-audit">Last completed by <strong>{{ $siteVisit->assessmentCompletedBy?->name ?? 'Former team member' }}</strong> on {{ $siteVisit->assessment_completed_at->format('d M Y, g:i A') }}.</p>
        @endif
        <div class="action-row assessment-actions"><button class="admin-button secondary" type="submit" name="assessment_state" value="draft">Save draft</button><button class="admin-button" type="submit" name="assessment_state" value="completed">Complete assessment</button></div>
    </form>
</section>
