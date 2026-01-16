import { Tag, Space } from "antd";

const TAG_COLORS = [
	"magenta",
	"red",
	"volcano",
	"orange",
	"gold",
	"lime",
	"green",
	"cyan",
	"blue",
	"geekblue",
	"purple",
];

const getColorById = (id) => {
	let hash = 0;
	for (let i = 0; i < id.length; i++) {
		hash = id.charCodeAt(i) + ((hash << 5) - hash);
	}
	return TAG_COLORS[Math.abs(hash) % TAG_COLORS.length];
};

export default function SpecialtyTags({
	specialties = [],
	selectedSpecialtyIds = [],
	onChange,
}) {
	if (specialties.length === 0) return null;

	return (
		<div style={{ marginBottom: 20, padding: 10 }}>
			<Space align="center" style={{ justifyContent: "center" }} wrap>
				{specialties.map((item) => {
					const id = String(item.id);
					const isSelected = selectedSpecialtyIds.includes(id);
					const color = getColorById(id);

					return (
						<Tag
							key={id}
							color={color}
							bordered={isSelected}
							style={{
								cursor: "pointer",
								padding: "6px 12px",
								borderRadius: 16,
								opacity: isSelected ? 1 : 0.75,
							}}
							onClick={() => onChange(id)}
						>
							{item.name}
						</Tag>
					);
				})}
			</Space>
		</div>
	);
}
