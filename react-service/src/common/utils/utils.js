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

export function normalizeText(str) {
	if (!str) return "";
	return str
		.normalize("NFD")
		.replace(/[\u0300-\u036f]/g, "")
		.replace(/đ/g, "d")
		.replace(/Đ/g, "D")
		.toLowerCase();
}

export const ClinicStatusMap = {
	AI_DETECTED: {
		icon: "ExclamationCircleFilled",
		tooltip: "Dữ liệu do AI trích xuất, chưa được xác thực.",
	},
	OWNER_VERIFIED: {
		icon: "CheckCircleFilled",
		tooltip: "Dữ liệu được chủ cơ sở xác thực.",
	},
	ADMIN_VERIFIED: {
		icon: "CheckCircleTwoTone",
		tooltip: "Dữ liệu do quản trị viên tổng hợp và chỉnh sửa.",
	},
	DEFAULT: {
		icon: "ExclamationCircleFilled",
		tooltip: "Dữ liệu do AI trích xuất, chưa được xác thực.",
	},
};

export const getStatusIcon = (status) => {
	return ClinicStatusMap[status]?.icon || ClinicStatusMap.DEFAULT.icon;
};

export const getStatusTooltip = (status) => {
	return ClinicStatusMap[status]?.tooltip || ClinicStatusMap.DEFAULT.tooltip;
};

export const extractLatLngFromGoogleMapUrl = (url) => {
	if (!url) return null;

	try {
		const qMatch = url.match(/[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
		if (qMatch) {
			return {
				lat: parseFloat(qMatch[1]),
				lng: parseFloat(qMatch[2]),
			};
		}

		const atMatch = url.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*)/);
		if (atMatch) {
			return {
				lat: parseFloat(atMatch[1]),
				lng: parseFloat(atMatch[2]),
			};
		}

		return null;
	} catch {
		return null;
	}
};
