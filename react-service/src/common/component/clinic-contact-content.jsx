import { Row, Col, Space, Typography, Image } from "antd";
import {
	PhoneOutlined,
	MailOutlined,
	EnvironmentOutlined,
} from "@ant-design/icons";
import { IMAGE_FALLBACK } from "../utils/image";

const { Title, Text, Link, Paragraph } = Typography;

export default function ClinicContactContent({ clinic }) {
	if (!clinic) return null;
	const { name, phone, email, lat, lng, image, address, description } =
		clinic;

	const latNum = parseFloat(lat);
	const lngNum = parseFloat(lng);

	const hasMap =
		!Number.isNaN(latNum) &&
		!Number.isNaN(lngNum) &&
		latNum >= -90 &&
		latNum <= 90 &&
		lngNum >= -180 &&
		lngNum <= 180;

	return (
		<Row gutter={24}>
			{/* LEFT */}
			<Col xs={24} md={12}>
				{hasMap ? (
					<iframe
						title="clinic-map"
						src={`https://www.google.com/maps?q=${lat},${lng}&z=16&output=embed`}
						width="100%"
						height="100%"
						style={{
							minHeight: 300,
							border: 0,
							borderRadius: 8,
							aspectRatio: "4 / 3",
							maxHeight: 350,
						}}
						loading="lazy"
					/>
				) : (
					<Image
						src={image}
						fallback={IMAGE_FALLBACK}
						alt={name}
						preview={true}
						style={{
							width: "100%",
							height: "100%",
							aspectRatio: "4 / 3",
							minHeight: 300,
							maxHeight: 350,
							objectFit: "cover",
							borderRadius: 8,
						}}
					/>
				)}
			</Col>

			{/* RIGHT */}
			<Col xs={24} md={12}>
				<Space direction="vertical" size={12}>
					<Title level={4} style={{ margin: 0, paddingTop: 20 }}>
						{name}
					</Title>

					{description && (
						<Space>
							<Paragraph type="secondary">
								{description}
							</Paragraph>
						</Space>
					)}

					{phone && (
						<Space>
							<PhoneOutlined />
							<Link href={`tel:${phone}`}>{phone}</Link>
						</Space>
					)}

					{email && (
						<Space>
							<MailOutlined />
							<Link href={`mailto:${email}`}>{email}</Link>
						</Space>
					)}

					{address && (
						<Space align="start">
							<EnvironmentOutlined />
							<Text>{address}</Text>
						</Space>
					)}
				</Space>
			</Col>
		</Row>
	);
}
