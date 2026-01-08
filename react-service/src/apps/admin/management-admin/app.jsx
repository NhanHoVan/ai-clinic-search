import { Tabs } from "antd";
import ClinicsTab from "./clinics-tab.jsx";
import ClinicSuggestionsTab from "./clinic-suggestions-tab.jsx";

const items = [
	{
		key: "1",
		label: "Quản lý clinic",
		children: <ClinicsTab />,
	},
	{
		key: "2",
		label: "Clinics gợi ý",
		children: <ClinicSuggestionsTab />,
	},
];

export default function App() {
	return <Tabs defaultActiveKey="1" items={items} type="card" />;
}
