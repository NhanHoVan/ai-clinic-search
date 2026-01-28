export default function ActionIcon({ Icon, enabled, color, onClick }) {
	const disabledColor = "#bfbfbf";
	return (
		<Icon
			style={{
				color: enabled ? color : disabledColor,
				cursor: enabled ? "pointer" : "not-allowed",
				transition: "all 0.2s ease",
			}}
			onClick={enabled ? onClick : undefined}
			onMouseEnter={(e) => {
				if (!enabled) return;
				e.currentTarget.style.transform = "scale(1.15)";
			}}
			onMouseLeave={(e) => {
				if (!enabled) return;
				e.currentTarget.style.transform = "scale(1)";
			}}
		/>
	);
}
