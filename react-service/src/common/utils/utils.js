// utils.js
export const maskPhone = (phone) => {
	if (!phone && phone !== "") return "";
	const s = String(phone);

	if (s.length <= 7) return s;

	const first3 = s.slice(0, 3);
	const last4 = s.slice(-4);

	return `${first3} *** ${last4}`;
};

export const maskEmail = (email) => {
	if (!email) return "";
	return email.replace(/^(.{2}).+(@.+)$/, "$1***$2");
};

export const getStatusIcon = (verifyStatus) => {
	switch (verifyStatus) {
		case "AI_DETECTED":
			return "🤖";
		case "OWNER_VERIFIED":
			return "✅";
		case "ADMIN_VERIFIED":
			return "⏳";
		default:
			return "❓";
	}
};

export function normalizeText(str) {
	if (!str) return "";
	return str
		.normalize("NFD")
		.replace(/[\u0300-\u036f]/g, "")
		.replace(/đ/g, "d")
		.replace(/Đ/g, "D")
		.toLowerCase();
}
