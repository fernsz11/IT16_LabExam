let lastEncrypted = ""; 

// --- Validation Helper ---
function isInputValid(isEncrypting) {
    const key = document.getElementById("key").value;
    const keyNum = parseInt(key);

    // Validate Key (1-25)
    if (key === "" || isNaN(keyNum) || keyNum < 1 || keyNum > 25) {
        alert("❌ Error: Please enter a valid Key (1–25).");
        return false;
    }

    if (isEncrypting) {
        const fullname = document.getElementById("fullname").value.trim();
        const year = document.getElementById("year").value.trim();
        const course = document.getElementById("course").value.trim();
        if (!fullname || !year || !course) {
            alert("❌ Error: All identity fields must be filled to encrypt.");
            return false;
        }
    } else {
        // Validate that there is actually something in the Output box to decrypt
        const currentOutput = document.getElementById("output").value.trim();
        if (!currentOutput) {
            alert("⚠️ Error: The Output box is empty! Nothing to decrypt.");
            return false;
        }
    }
    return true;
}

// Caesar Logic
function caesarShift(text, shift) {
    return text.replace(/[a-z]/gi, (char) => {
        const base = char === char.toUpperCase() ? 65 : 97;
        return String.fromCharCode(((char.charCodeAt(0) - base + shift + 26) % 26) + base);
    });
}

function encryptText() {
    if (!isInputValid(true)) return;

    const fullname = document.getElementById("fullname").value.trim();
    const year = document.getElementById("year").value.trim();
    const course = document.getElementById("course").value.trim();
    const shift = parseInt(document.getElementById("key").value);

    const combined = `${fullname} | ${year} | ${course}`;
    document.getElementById("plaintext").value = combined;
    
    lastEncrypted = caesarShift(combined, shift);
    document.getElementById("output").value = lastEncrypted;
}

function decryptText() {
    if (!isInputValid(false)) return;

    const shift = parseInt(document.getElementById("key").value);
    const currentOutput = document.getElementById("output").value;

    // We use a negative shift to go backwards
    const decrypted = caesarShift(currentOutput, -shift);
    
    // This updates the Output box to show the decrypted result
    document.getElementById("output").value = decrypted;
}

function clearFields() {
    ["fullname", "year", "course", "plaintext", "output"].forEach(id => {
        document.getElementById(id).value = "";
    });
    document.getElementById("key").value = "3";
    lastEncrypted = "";
}