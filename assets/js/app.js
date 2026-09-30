(() => {
    const open = document.querySelector("[data-open-chat]");
    const close = document.querySelector("[data-close-chat]");
    const chat = document.querySelector("[data-chat]");
    const form = document.querySelector("[data-chat-form]");
    const input = document.querySelector("[data-chat-input]");
    const log = document.querySelector("[data-chat-log]");
    const status = document.querySelector("[data-chat-status]");
    const mic = document.querySelector("[data-mic]");
    if (!chat || !form || !input || !log) return;

    const show = (value) => {
        chat.hidden = !value;
        if (open) open.hidden = value;
        if (value) input.focus();
    };
    open?.addEventListener("click", () => show(true));
    close?.addEventListener("click", () => show(false));

    const append = (text, mine) => {
        const node = document.createElement("div");
        node.className = mine ? "me" : "ai";
        node.textContent = text;
        log.appendChild(node);
        log.scrollTop = log.scrollHeight;
    };

    const count = () => {
        if (status) status.textContent = input.value.length ? input.value.length + " ตัวอักษร" : "";
    };
    input.addEventListener("input", () => {
        input.style.height = "auto";
        input.style.height = Math.min(input.scrollHeight, 160) + "px";
        count();
    });

    form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        append(text, true);
        input.value = "";
        count();
        const body = new FormData(form);
        body.set("message", text);
        try {
            const response = await fetch(form.action, { method: "POST", body, headers: { Accept: "application/json" } });
            const data = await response.json();
            append(data.reply || "ตอบกลับไม่ได้ในตอนนี้ค่ะ", false);
        } catch (error) {
            append("ส่งข้อความไม่สำเร็จ ลองใหม่อีกครั้งค่ะ", false);
        }
    });

    input.addEventListener("keydown", (event) => {
        if (event.key === "Enter" && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    document.querySelectorAll("[data-prompt]").forEach((button) => {
        button.addEventListener("click", () => {
            input.value = button.getAttribute("data-prompt") || "";
            form.requestSubmit();
        });
    });

    let recognition = null;
    mic?.addEventListener("click", () => {
        const Speech = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!Speech) {
            append("เบราว์เซอร์นี้ยังไม่รองรับการพิมพ์ด้วยเสียง แนะนำให้ใช้ Google Chrome หรือ Microsoft Edge ค่ะ", false);
            return;
        }
        if (recognition) {
            recognition.stop();
            recognition = null;
            mic.classList.remove("on");
            return;
        }
        recognition = new Speech();
        recognition.lang = "th-TH";
        recognition.continuous = true;
        recognition.interimResults = true;
        const base = input.value;
        recognition.onresult = (event) => {
            let finalText = "";
            let interim = "";
            for (let i = 0; i < event.results.length; i += 1) {
                const piece = event.results[i][0].transcript;
                if (event.results[i].isFinal) finalText += piece;
                else interim += piece;
            }
            input.value = (base + " " + finalText + interim).trim();
            count();
        };
        recognition.onerror = (event) => {
            if (event.error === "not-allowed") {
                append("ไม่ได้รับอนุญาตให้ใช้ไมโครโฟน กรุณาอนุญาตการเข้าถึงไมโครโฟนในเบราว์เซอร์ค่ะ", false);
            }
        };
        recognition.onend = () => {
            recognition = null;
            mic.classList.remove("on");
        };
        recognition.start();
        mic.classList.add("on");
    });
})();
