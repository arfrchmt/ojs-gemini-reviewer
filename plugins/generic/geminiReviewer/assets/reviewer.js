(function() {
    function injectGeminiButton() {
        // Cari form modal sendReviewsForm
        var sendForm = document.getElementById("sendReviewsForm");
        if (!sendForm) return;

        // Cek jika tombol sudah ada
        if (document.getElementById("btn-exec-gemini-review")) return;

        // Cari container textarea personalMessage
        var emailPane = document.getElementById("sendReviews-email-pane");
        if (!emailPane) return;

        var bar = document.createElement("div");
        bar.id = "gemini-modal-bar";
        bar.style.cssText = "margin: 10px 0 15px 0; padding: 10px 14px; background: #eff6ff; border: 1.5px solid #38bdf8; border-radius: 6px; display: flex; align-items: center; justify-content: space-between;";
        bar.innerHTML = '<div style="display: flex; align-items: center;">' +
                            '<span style="font-size: 20px; margin-right: 8px;">🤖</span>' +
                            '<div>' +
                                '<strong style="font-size: 13px; color: #0369a1; display: block;">Gemini AI Reviewer</strong>' +
                                '<span style="font-size: 11px; color: #64748b;">Generate ulasan naskah otomatis & masukkan ke pesan</span>' +
                            '</div>' +
                        '</div>' +
                        '<button type="button" id="btn-exec-gemini-review" style="background: #2563eb; color: #ffffff; font-weight: bold; border: none; border-radius: 4px; padding: 8px 14px; font-size: 12px; cursor: pointer;">' +
                            '⚡ Generate & Sisipkan Review AI' +
                        '</button>';

        emailPane.insertBefore(bar, emailPane.firstChild);

        document.getElementById("btn-exec-gemini-review").addEventListener("click", function(e) {
            e.preventDefault();

            if (!confirm("Jalankan analisis AI untuk naskah ini? Ulasan akan otomatis disisipkan ke form email dan file .txt diunduh.")) {
                return;
            }

            var btn = this;
            var origText = btn.innerText;
            btn.disabled = true;
            btn.style.opacity = "0.6";
            btn.innerText = "⏳ Sedang menelaah naskah...";

            var ajaxUrl = window.geminiAjaxUrl;

            fetch(ajaxUrl, {
                method: "POST",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.style.opacity = "1";
                btn.innerText = origText;

                if (data.status) {
                    var reviewText = data.review;
                    var htmlFormatted = "<p><br></p><p><strong>=== AI PEER REVIEW ASSESSMENT ===</strong></p>" + 
                        "<p>" + reviewText.split("\n\n").join("</p><p>").split("\n").join("<br>") + "</p>";

                    var inserted = false;

                    // 1. Cek TinyMCE berdasarkan ID #personalMessage
                    if (typeof tinyMCE !== "undefined") {
                        var ed = tinyMCE.get("personalMessage");
                        if (ed) {
                            ed.setContent(ed.getContent() + htmlFormatted);
                            inserted = true;
                        } else if (tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
                            tinyMCE.activeEditor.setContent(tinyMCE.activeEditor.getContent() + htmlFormatted);
                            inserted = true;
                        }
                    }

                    // 2. Fallback jika iframe TinyMCE ada di modal
                    if (!inserted) {
                        var iframes = document.querySelectorAll("#sendReviewsForm iframe, .ui-dialog iframe");
                        for (var i = 0; i < iframes.length; i++) {
                            var doc = iframes[i].contentDocument || iframes[i].contentWindow.document;
                            if (doc && doc.body) {
                                doc.body.innerHTML += htmlFormatted;
                                inserted = true;
                                break;
                            }
                        }
                    }

                    // 3. Fallback textarea plain
                    var ta = document.getElementById("personalMessage");
                    if (ta) {
                        ta.value += "\n\n=== AI PEER REVIEW ASSESSMENT ===\n" + reviewText;
                    }

                    // 4. Download file .txt
                    var blob = new Blob([reviewText], {type: "text/plain;charset=utf-8"});
                    var dl = document.createElement("a");
                    dl.href = URL.createObjectURL(blob);
                    dl.download = "AI_Review_Submission_" + data.submissionId + ".txt";
                    document.body.appendChild(dl);
                    dl.click();
                    document.body.removeChild(dl);

                    alert("Ulasan AI berhasil disisipkan ke editor dan file .txt telah diunduh.");
                } else {
                    alert("Pesan: " + data.message);
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.style.opacity = "1";
                btn.innerText = origText;
                alert("Terjadi kesalahan koneksi API: " + err);
            });
        });
    }

    // Interval polling untuk memeriksa saat modal muncul di layar
    setInterval(injectGeminiButton, 500);
})();
