import { Select } from "antd";

export default function SelectField({
	label,
	value,
	onChange,
	options = [],
	placeholder,
	disabled = false,
	size = "large",
}) {
	return (
		<div className="flex-col">
			<span className="label-input">{label}</span>
			<Select
				showSearch
				size={size}
				allowClear
				placeholder={placeholder}
				value={value || undefined}
				onChange={onChange}
				disabled={disabled}
				optionFilterProp="label"
				filterSort={(a, b) =>
					(a?.label ?? "").localeCompare(b?.label ?? "")
				}
				options={options}
				style={{ width: "100%" }}
			/>
		</div>
	);
}
