import { useState, useEffect, useCallback } from "react";
import { Tabs, Typography, message } from "antd";
import { useWordPress } from "@/hooks/use-wordpress";
import ClinicsTab from "./clinics-tab.jsx";
import ClinicSuggestionReviewTab from "./clinic-suggestions-review-tab.jsx";

const { Title } = Typography;

export default function App() {
	const { apiFetch } = useWordPress();
	const [specialties, setSpecialties] = useState([]);

	const fetchSpecialties = useCallback(async () => {
		try {
			const data = await apiFetch("/specialties", { method: "GET" });

			setSpecialties(
				Array.isArray(data?.specialties) ? data.specialties : [],
			);
		} catch (e) {
			message.error(e?.message || "Không thể tải danh sách chuyên khoa");
		}
	}, [apiFetch]);

	useEffect(() => {
		fetchSpecialties();
	}, [fetchSpecialties]);

	const items = [
		{
			key: "1",
			label: <strong>Phòng khám</strong>,
			children: <ClinicsTab specialties={specialties} />,
		},
		{
			key: "2",
			label: <strong>Đề xuất phòng khám</strong>,
			children: <ClinicSuggestionReviewTab specialties={specialties} />,
		},
	];

	return (
		<div style={{ margin: "30px auto" }}>
			<Title level={2} style={{ textAlign: "center", marginBottom: 20 }}>
				Quản lý phòng khám & đề xuất chỉnh sửa
			</Title>
			<Tabs defaultActiveKey="1" items={items} type="card" />
		</div>
	);
}
