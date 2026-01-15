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

const getColorByCode = (code) => {
	let hash = 0;
	for (let i = 0; i < code.length; i++) {
		hash = code.charCodeAt(i) + ((hash << 5) - hash);
	}
	return TAG_COLORS[Math.abs(hash) % TAG_COLORS.length];
};

export default function SpecialtyTags({
	specialties,
	selectedSpecialties,
	onChange,
}) {
	if (!specialties || specialties.length === 0) {
		return null;
	}

	return (
		<div style={{ marginBottom: 20, padding: 10 }}>
			<Space align="center" style={{ justifyContent: "center" }} wrap>
				{specialties.map((item) => {
					const isSelected = selectedSpecialties.includes(item.code);
					const color = getColorByCode(item.code);

					return (
						<Tag
							key={item.code}
							color={color}
							bordered={isSelected}
							style={{
								cursor: "pointer",
								padding: "6px 12px",
								borderRadius: 16,
								transition: "all 0.2s ease",
							}}
							onClick={() => onChange(item.code)}
						>
							{item.name}
						</Tag>
					);
				})}
			</Space>
		</div>
	);
}
