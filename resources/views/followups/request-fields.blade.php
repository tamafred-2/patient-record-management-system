<input type="hidden" name="assessment_version" value="{{ $visit->vitalSign->lock_version }}">
<input type="hidden" name="submission_token" value="{{ (string) Illuminate\Support\Str::uuid() }}">
<x-textarea name="request_reason" label="Follow-up findings and reason for test" id="{{ $id }}" required maxlength="2000" rows="4" />
<label class="flex gap-3"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I reviewed the assessment and confirm this request to laboratory.</span></label>
