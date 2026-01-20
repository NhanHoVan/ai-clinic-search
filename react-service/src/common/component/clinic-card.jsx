import { Card, Image, Tooltip, Rate } from "antd";
import {
	ExclamationCircleFilled,
	CheckCircleFilled,
	CheckCircleTwoTone,
	CopyOutlined,
	EyeOutlined,
} from "@ant-design/icons";
import { IMAGE_FALLBACK } from "../utils/image";
import {
	maskPhone,
	maskEmail,
	getStatusIcon,
	getStatusTooltip,
} from "../utils/utils";

const IconMap = {
	ExclamationCircleFilled: (
		<ExclamationCircleFilled style={{ color: "#ff5716" }} />
	),
	CheckCircleFilled: <CheckCircleFilled style={{ color: "#52c41a" }} />,
	CheckCircleTwoTone: <CheckCircleTwoTone twoToneColor="#52c41a" />,
};

const { Meta } = Card;

export default function ClinicCard({ clinic, loading, onCopy, onViewDetail }) {
	const actions = [];
	if (onCopy) {
		actions.push(
			<CopyOutlined
				key="copy"
				style={{ color: "#4ecdc4" }}
				onClick={() => onCopy(clinic.name)}
			/>
		);
	}
	if (onViewDetail) {
		actions.push(
			<EyeOutlined
				key="detail"
				style={{ color: "#4e50cdff" }}
				onClick={() => onViewDetail(clinic.id)}
			/>
		);
	}

	return (
		<Card
			styles={{
				body: {
					padding: 15,
				},
			}}
			cover={
				<Image
					style={{ objectFit: "cover" }}
					height={200}
					draggable={false}
					alt={clinic.name}
					src={clinic.image}
					fallback={IMAGE_FALLBACK}
				/>
			}
			actions={actions.length > 0 ? actions : undefined}
			hoverable
			loading={loading}
		>
			<Meta
				title={
					<span>
						<Tooltip title={getStatusTooltip(clinic.verify_status)}>
							{IconMap[getStatusIcon(clinic.verify_status)]}
						</Tooltip>{" "}
						<Tooltip title={clinic.name}>{clinic.name}</Tooltip>
					</span>
				}
				description={
					clinic.description && (
						<p>
							{clinic.description.length > 80
								? clinic.description.substring(0, 80) + "..."
								: clinic.description}
						</p>
					)
				}
				className="meta-clinic-card"
			/>
			<p>
				<strong>Địa chỉ:</strong> {clinic.address}
			</p>
			{clinic.phone && (
				<p>
					<strong>Điện thoại:</strong> {maskPhone(clinic.phone)}
				</p>
			)}
			{clinic.email && (
				<p>
					<strong>Email:</strong> {maskEmail(clinic.email)}
				</p>
			)}
			{clinic.rating && (
				<div style={{ marginBottom: 8 }}>
					<Rate
						disabled
						allowHalf
						value={clinic.rating}
						style={{ fontSize: 14, marginRight: 4 }}
					/>
					<span style={{ fontSize: 13, color: "#888" }}>
						({clinic.user_rating_count} reviews)
					</span>
				</div>
			)}
		</Card>
	);
}
