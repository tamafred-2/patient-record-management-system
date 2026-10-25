<input type="hidden" name="assessment_version" value="{{ $visit->vitalSign->lock_version }}">
<input type="hidden" name="submission_token" value="{{ (string) Illuminate\Support\Str::uuid() }}">
<div><label for="{{ $id }}" class="mb-2 block font-medium">Follow-up findings and reason for test</label><textarea id="{{ $id }}" name="request_reason" required maxlength="2000" rows="4" class="w-full rounded-lg border border-slate-300 p-3">{{ old('request_reason') }}</textarea></div>
<label class="flex gap-3"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I reviewed the assessment and confirm this request to laboratory.</span></label>
