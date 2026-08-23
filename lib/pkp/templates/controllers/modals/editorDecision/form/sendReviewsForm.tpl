{**
 * templates/controllers/modals/editorDecision/form/sendReviewsForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Form used to send reviews to author
 *
 * @uses $revisionsEmail string Email body for requesting revisions that don't
 *   require another round of review.
 * @uses $resubmitEmail string Email body for asking the author to resubmit for
 *   another round of review.
 *}
<script type="text/javascript">
    $(function() {ldelim}
        $('#sendReviews').pkpHandler(
            '$.pkp.controllers.modals.editorDecision.form.EditorDecisionFormHandler',
            {ldelim}
                {if $revisionsEmail}
                    revisionsEmail: {$revisionsEmail|json_encode},
                {/if}
                {if $resubmitEmail}
                    resubmitEmail: {$resubmitEmail|json_encode},
                {/if}
                peerReviewUrl: {$peerReviewUrl|json_encode}
            {rdelim}
        );
    {rdelim});
</script>

<form class="pkp_form" id="sendReviews" method="post" action="{url op=$saveFormOperation}" >
    {csrf}
    <input type="hidden" name="submissionId" value="{$submissionId|escape}" />
    <input type="hidden" name="stageId" value="{$stageId|escape}" />
    <input type="hidden" name="reviewRoundId" value="{$reviewRoundId|escape}" />

    {* Set the decision or allow the decision to be selected *}
    {if $decision != $smarty.const.SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS && $decision != $smarty.const.SUBMISSION_EDITOR_DECISION_RESUBMIT}
        <input type="hidden" name="decision" value="{$decision|escape}" />
    {else}
        {if $decision == $smarty.const.SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS}
            {assign var="checkedRevisions" value="1"}
        {elseif $decision == $smarty.const.SUBMISSION_EDITOR_DECISION_RESUBMIT}
            {assign var="checkedResubmit" value="1"}
        {/if}
        {fbvFormSection title="editor.review.newReviewRound"}
            <ul class="checkbox_and_radiobutton">
                {fbvElement type="radio" id="decisionRevisions" name="decision" value=$smarty.const.SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS checked=$checkedRevisions label="editor.review.NotifyAuthorRevisions"}
                {fbvElement type="radio" id="decisionResubmit" name="decision" value=$smarty.const.SUBMISSION_EDITOR_DECISION_RESUBMIT checked=$checkedResubmit label="editor.review.NotifyAuthorResubmit"}
            </ul>
        {/fbvFormSection}
    {/if}

    {capture assign="sendEmailLabel"}{translate key="editor.submissionReview.sendEmail" authorName=$authorName}{/capture}
    {if $skipEmail}
        {assign var="skipEmailSkip" value=true}
    {else}
        {assign var="skipEmailSend" value=true}
    {/if}
    {fbvFormSection title="common.sendEmail"}
        <ul class="checkbox_and_radiobutton">
            {fbvElement type="radio" id="skipEmail-send" name="skipEmail" value="0" checked=$skipEmailSend label=$sendEmailLabel translate=false}
            {fbvElement type="radio" id="skipEmail-skip" name="skipEmail" value="1" checked=$skipEmailSkip label="editor.submissionReview.skipEmail"}
        </ul>
    {/fbvFormSection}

    <div id="sendReviews-emailContent">
        {capture assign=geminiReviewUrl}{url router=$smarty.const.ROUTE_PAGE page="geminiReviewerHandler" op="generateReview" submissionId=$submissionId}{/capture}

        <div style="margin: 10px 0 15px 0; padding: 10px 14px; background: #eff6ff; border: 1.5px solid #38bdf8; border-radius: 6px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center;">
                <span style="font-size: 20px; margin-right: 8px;">🤖</span>
                <div>
                    <strong style="font-size: 13px; color: #0369a1; display: block;">Gemini AI Reviewer</strong>
                    <span style="font-size: 11px; color: #64748b;">Generate ulasan naskah otomatis & masukkan ke pesan</span>
                </div>
            </div>
            <button type="button" class="pkp_button" style="background: #2563eb; color: #ffffff; font-weight: bold; border: none; padding: 7px 14px; border-radius: 4px; cursor: pointer;" onclick="execGeminiAction(this, '{$geminiReviewUrl|escape:"javascript"}', '{$submissionId|escape:"javascript"}')">
                ⚡ Generate & Sisipkan Review AI
            </button>
        </div>

        {* Message to author textarea *}
        {fbvFormSection for="personalMessage"}
            {fbvElement type="textarea" name="personalMessage" id="personalMessage" value=$personalMessage rich=true variables=$allowedVariables variablesType=$allowedVariablesType}
        {/fbvFormSection}

        {* Button to add reviews to the email automatically *}
        {if $reviewsAvailable}
            {fbvFormSection}
                <a id="importPeerReviews" href="#" class="pkp_button">
                    <span class="fa fa-plus" aria-hidden="true"></span>
                    {translate key="submission.comments.addReviews"}
                </a>
            {/fbvFormSection}
        {/if}

        {if isset($reviewers)}
            {include file="controllers/modals/editorDecision/form/bccReviewers.tpl"
                reviewers=$reviewers
                selected=$bccReviewers
            }
        {/if}
    </div>

    {** Some decisions can be made before review is initiated (i.e. no attachments). **}
    {if $reviewRoundId}
        <div id="attachments" style="margin-top: 30px;">
            {capture assign=reviewAttachmentsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.files.attachment.EditorSelectableReviewAttachmentsGridHandler" op="fetchGrid" submissionId=$submissionId stageId=$stageId reviewRoundId=$reviewRoundId escape=false}{/capture}
            {load_url_in_div id="reviewAttachmentsGridContainer" url=$reviewAttachmentsGridUrl}
        </div>
    {/if}

    {fbvFormButtons submitText="editor.submissionReview.recordDecision"}
</form>

<script type="text/javascript">
function execGeminiAction(btn, ajaxUrl, subId) {ldelim}
    if (!confirm("Jalankan telaah naskah otomatis dengan Gemini AI? Ulasan akan langsung disisipkan ke pesan revisi dan berkas .txt diunduh.")) return;

    var origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = "⏳ Menganalisis naskah...";

    $.ajax({ldelim}
        url: ajaxUrl,
        type: "POST",
        dataType: "json",
        success: function(data) {ldelim}
            btn.disabled = false;
            btn.innerHTML = origText;

            if (data.status) {ldelim}
                var reviewText = data.review;
                var htmlInsert = "<p><br></p><p><strong>=== AI PEER REVIEW ASSESSMENT ===</strong></p>" + 
                    "<p>" + reviewText.split("\n\n").join("</p><p>").split("\n").join("<br>") + "</p>";

                // 1. Update TinyMCE Editor
                if (typeof tinyMCE !== "undefined") {ldelim}
                    var ed = tinyMCE.get("personalMessage");
                    if (ed) {ldelim}
                        ed.setContent(ed.getContent() + htmlInsert);
                    {rdelim} else if (tinyMCE.activeEditor) {ldelim}
                        tinyMCE.activeEditor.setContent(tinyMCE.activeEditor.getContent() + htmlInsert);
                    {rdelim}
                {rdelim}

                // 2. Fallback plain textarea
                var ta = document.getElementById("personalMessage");
                if (ta) {ldelim}
                    ta.value += "\n\n=== AI PEER REVIEW ASSESSMENT ===\n" + reviewText;
                {rdelim}

                // 3. Download berkas .txt otomatis
                var blob = new Blob([reviewText], {ldelim}type: "text/plain;charset=utf-8"{rdelim});
                var dl = document.createElement("a");
                dl.href = URL.createObjectURL(blob);
                dl.download = "AI_Review_Submission_" + subId + ".txt";
                document.body.appendChild(dl);
                dl.click();
                document.body.removeChild(dl);

                alert("Ulasan AI berhasil dimasukkan ke pesan dan file .txt telah diunduh.");
            {rdelim} else {ldelim}
                alert("Pesan: " + data.message);
            {rdelim}
        {rdelim},
        error: function(xhr, status, err) {ldelim}
            btn.disabled = false;
            btn.innerHTML = origText;
            alert("Gagal memproses review: " + err);
        {rdelim}
    {rdelim});
{rdelim}
</script>
