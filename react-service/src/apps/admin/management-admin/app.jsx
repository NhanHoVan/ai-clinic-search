import { Tabs, Typography } from "antd";
import ClinicsTab from "./clinics-tab.jsx";
import ClinicSuggestionReviewTab from "./clinic-suggestions-tab.jsx";

const { Title } = Typography;

const items = [
	{
		key: "1",
		label: <strong>Phòng khám</strong>,
		children: <ClinicsTab />,
	},
	{
		key: "2",
		label: <strong>Đề xuất phòng khám</strong>,
		children: <ClinicSuggestionReviewTab />,
	},
];

export default function App() {
	return (
		<div style={{ margin: "30px auto" }}>
			<Title level={2} style={{ textAlign: "center", marginBottom: 20 }}>
				Quản lý phòng khám & đề xuất chỉnh sửa
			</Title>
			<Tabs defaultActiveKey="1" items={items} type="card" />
		</div>
	);
}
