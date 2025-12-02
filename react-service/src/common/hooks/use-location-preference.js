import { useState, useEffect } from "react";

const STORAGE_KEY = "user_location_pref";

export function useLocationPreference() {
	const [pref, setPref] = useState({
		province: null,
		district: null,
	});

	useEffect(() => {
		const saved = localStorage.getItem(STORAGE_KEY);
		if (saved) {
			try {
				setPref(JSON.parse(saved));
			} catch {}
		}
	}, []);

	useEffect(() => {
		localStorage.setItem(STORAGE_KEY, JSON.stringify(pref));
	}, [pref]);

	return {
		pref,
		setProvince: (province) => setPref((p) => ({ ...p, province })),
		setDistrict: (district) => setPref((p) => ({ ...p, district })),
	};
}
